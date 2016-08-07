<?php
/**
 * Description of publisher
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Shared\Mail as Mail;
use Shared\Utils as Utils;
use Framework\ArrayMethods as ArrayMethods;

class Publisher extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data"));
        $view = $this->getActionView(); $commissions = []; $clicks = 0;

        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-1 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query = [
            "pid" => $this->user->id,
            "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ];
        
        $clickCol = Registry::get("MongoDB")->clicks;
        $total_clicks = $clickCol->count($query);

        $clicks = $clickCol->find($query,['adid', 'cookie', 'ipaddr', 'referer']);

        $view->set("start", $start)
            ->set("end", $end)
            ->set("performance", $this->perf($clicks, $this->user))
            ->set("clicks", $total_clicks);
    }

    /**
     * @before _secure
     */
    public function campaigns() {
    	$this->seo(array("title" => "Campaigns"));$view = $this->getActionView();

        $limit = RequestMethods::get("limit", 20);
        $page = RequestMethods::get("page", 1);
        $query = ["live = ?" => true, "org_id = ?" => $this->org->_id];
    	
        $ads = \Ad::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Ad::count($query); $in = [];
        $categories = \Category::all(["org_id = ?" => $this->org->_id], ['name', '_id']);

        foreach ($ads as $a) {
            $in[] = $a->_id;
        }
        $clickCol = Registry::get("MongoDB")->clicks;
        $records = $clickCol->find(['adid' => ['$in' => $in]], ['adid', 'pid', 'ipaddr', 'referer']);
        $clicks = Click::classify($records, 'adid');

        $result = [];
        foreach ($clicks as $key => $value) {
            $result[$key] = count($value);
        }
        arsort($result);
    	
        $view->set([
            'limit' => $limit, 'page' => $page,
            'count' => $count, 'ads' => $ads,
            'categories' => $categories, 'clicks' => $result
        ]);
    }

    /**
     * @before _secure
     */
    public function reports() {
        $this->seo(array("title" => "Campaigns"));$view = $this->getActionView();
        
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $limit = RequestMethods::get("limit", 30);
        $page = RequestMethods::get("page", 1);

        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        $query = [
            "user_id" => $this->user->_id,
            "created" => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ];

        $performances = \Performance::all($query, ['created', 'clicks', 'revenue'], 'created', 'desc');
        $links = \Link::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Link::count($query);

        // find clicks
        $clickCol = Registry::get("MongoDB")->clicks;
        $records = $clickCol->find([
            'pid' => $query['user_id'], 'created' => $query['created']
        ], ['adid', 'ipaddr', 'referer']);
        
        $view->set([
            'limit' => $limit,
            'page' => $page,
            'count' => $count,
            'start' => $start,
            'end' => $end,
            'links' => $links,
            'performances' => $performances,
            'clicks' => Click::classify($records, 'adid')
        ]);
    }

    /**
     * @before _secure
     */
    public function account() {
        $this->seo(array("title" => "Account"));
        $view = $this->getActionView();

        $transactions = \Transaction::all(['user_id = ?' => $this->user->_id], ['amount', 'currency', 'ref', 'created']);
        $view->set("transactions", $transactions);
        $user = $this->user; $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $name = RequestMethods::post('name');
                    $currency = RequestMethods::post('currency', 'INR');

                    $user->name = $name; $user->currency = $currency;
                    $user->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                case 'bank':
                    $meta = $user->getMeta();
                    $meta['bank'] = [
                        'name' => RequestMethods::post('account_bank', ''),
                        'ifsc' => RequestMethods::post('account_code', ''),
                        'account_no' => RequestMethods::post('account_number', ''),
                        'account_owner' => RequestMethods::post('account_owner', '')
                    ];
                    $user->meta = $meta; $user->save();
                    $view->set('message', 'Bank
                     Info Updated!!');
                    break;

                case 'payout':
                    $meta = $user->getMeta();
                    $meta['payout'] = [
                        'paypal' => RequestMethods::post('paypal', ''),
                        'payoneer' => RequestMethods::post('payoneer', ''),
                        'paytm' => RequestMethods::post('paytm', '')
                    ];
                    $user->meta = $meta; $user->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                default:
                    break;
            }
            $this->setUser($user);
        }
    }

    /**
     * @before _secure
     */
    public function createLink() {
        $this->JSONView();
        $view = $this->getActionView();

        $adid = RequestMethods::post("adid");
        if (!$adid) return $view->set('message', "Invalid Request");
        $ad = \Ad::first(["_id = ?" => $adid, "live = ?" => true], ['_id', 'title']);
        if (!$ad) {
            return $view->set('message', "Invalid Request");
        }
        if (RequestMethods::post("domain")) {
            $domain = RequestMethods::post("domain");
        } else if (count($this->org->tdomains) > 0) {
            $domain = $this->array_random($this->org->tdomains);
        } else {
            $domain = 'dobolly.com';
        }
        $link = Link::first(["ad_id = ?" => $ad->_id, "user_id = ?" => $this->user->_id], ['domain', '_id']);
        if ($link) {
            $view->set('message', $ad->title.' <br><a href="http://'.$domain.'/'.$link->_id.'" target="_blank">http://'.$domain.'/'.$link->_id.'<a>');
            return;
        }

        $link = new Link([
            'user_id' => $this->user->_id,
            'ad_id' => $ad->_id,
            'domain' => $domain,
            'app' => $this->org->domain,
            'live' => true
        ]);
        $link->save();
        $view->set('message', $ad->title.'<br><a href="'.$link->getUrl().'" target="_blank">'.$link->getUrl().'<a>');

    }

    protected function array_random($arr, $num = 1) {
        shuffle($arr);
        
        $r = array();
        for ($i = 0; $i < $num; $i++) {
            $r[] = $arr[$i];
        }
        return $num == 1 ? $r[0] : $r;
    }

    /**
     * @before _admin
     */
    public function payments() {
        $this->seo(array("title" => "Payments"));
        $view = $this->getActionView();

        $users = \User::all(['type = ?' => 'publisher', 'org_id' => $this->org->_id]);
        $payments = [];
        foreach ($users as $u) {
            $lastTransaction = \Transaction::first(['user_id = ?' => $u->_id], [], 'created', 'desc');

            $query = ['user_id = ?' => $u->_id];
            if ($lastTransaction) {
                $query['created'] = ['$gt' => $lastTransaction->created];
            }
            $performances = \Performance::all($query);
            $payment = 0.00;
            foreach ($performances as $p) {
                $payment += $p->revenue;
            }

            $payments[] = ArrayMethods::toObject([
                'user_id' => $u->getMongoID(),
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'amount' => $payment,
                'bank' => isset($u->meta['bank']) ? $u->meta['bank'] : []
            ]);
        }
        
        $view->set('payments', $payments);
    }

    /**
     * @before _admin
     */
    public function add() {
        $this->seo(array("title" => "Add Publisher"));$view = $this->getActionView();

        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $user = \User::addNew('publisher', $this->org, $view);
            if (!$user) return;
            $user->meta = [
                'campaign' => [
                    'model' => RequestMethods::post('model'),
                    'rate' => RequestMethods::post('rate', null)
                ]
            ];
            $user->save();

            Mail::send([
                'user' => $user,
                'template' => 'pubRegister',
                'subject' => 'Publisher at '. $this->org->name,
                'app' => $this->org->domain,
                'subdomain' => $this->org->domain,
                'team' => $this->org->name
            ]);
            $user->password = sha1($user->password);
            $user->live = 1;
            $user->save();
            
            $this->redirect("/publisher/manage.html");
        }
    }

    /**
     * @before _admin
     */
    public function manage() {
        $this->seo(array("title" => "List Publisher"));$view = $this->getActionView();

        $publishers = \User::all(["type = ?" => "publisher", "org_id = ?" => $this->org->_id], [], 'created', 'desc');

        $view->set("publishers", $publishers);
    }

    /**
     * @before _admin
     */
    public function update($id) {
        $this->JSONView(); $view = $this->getActionView();
        $p = \User::first(["_id = ?" => $id, "org_id = ?" => $this->org->_id]);
        if (!$p || RequestMethods::type() !== 'POST') {
            return $view->set('message', 'Invalid Request!!');
        }

        foreach ($_POST as $key => $value) {
            $p->$key = $value;
        }
        $p->save();
        $view->set('message', 'Updated successfully!!');
    }

    /**
     * @protected
     */
    public function _admin() {
        parent::_secure();
        if ($this->user->type !== 'admin' || !$this->org) {
            $this->noview();
            throw new \Framework\Router\Exception\Controller("Invalid Request");
        }
        $this->setLayout("layouts/admin");
    }

    /**
     * Returns data of clicks, impressions, payouts for publishers with custom date range
     * @before _secure
     */
    public function performance() {
        $this->JSONview();$view = $this->getActionView();
        
        $start = RequestMethods::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RequestMethods::get("end", strftime("%Y-%m-%d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        
        $find = Performance::overall($dateQuery, $this->user);
        $find["total_payouts"] = $this->user->convert($find["total_payouts"]);
        $view->set($find);
    }

    /**
     * @before _secure
     */
    public function platforms() {
        $this->seo(array("title" => "List of Platforms")); $view = $this->getActionView();

        if (RequestMethods::type() === 'POST') {
            $pid = RequestMethods::post('pid');
            try {
                if ($pid) {
                    $p = \Platform::first(['_id = ?' => $pid]);
                } else {
                    $p = new \Platform([
                        'user_id' => $this->user->_id
                    ]);
                }
                $p->url = RequestMethods::post('url');
                $meta = $p->meta;
                $meta['category'] = RequestMethods::post('category', ['386']);
                $meta['type'] = RequestMethods::post('type', '');
                $p->meta = $meta;
                $p->save();

                $view->set('message', 'Platform saved successfully!!');
            } catch (\Exception $e) {
                $view->set('message', $e->getMessage());
            }
        }
        $platforms = \Platform::all(["user_id = ?" => $this->user->_id]);
        $view->set("platforms", $platforms);
        $categories = \Category::all(['org_id' => $this->org->_id]);
        $view->set('categories', $categories);
    }

    /**
     * @before _admin
     */
    public function info($id) {
        $this->seo(array("title" => "Publisher Edit"));
        $view = $this->getActionView();

        $publisher = User::first(["_id = ?" => $id, "org_id = ?" => $this->org->id]);
        $lastTransaction = \Transaction::first(['user_id = ?' => $publisher->_id], [], 'created', 'desc');
        $performances = \Performance::all(['user_id = ?' => $publisher->_id, 'created' => ['$gt' => $lastTransaction->created]]);
        $payment = 0.00;
        foreach ($performances as $p) {
            $payment += $p->revenue;
        }
        $view->set("errors", []);
        if (RequestMethods::type() == 'POST') {
            $action = RequestMethods::post('action', '');
            switch ($action) {
                case 'account':
                    $name = RequestMethods::post('name');
                    $currency = RequestMethods::post('currency', 'INR');

                    $user->name = $name; $user->currency = $currency;
                    $user->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RequestMethods::post('password');
                    $new = RequestMethods::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                case 'bank':
                    $meta = $user->getMeta();
                    $meta['bank'] = [
                        'name' => RequestMethods::post('account_bank', ''),
                        'ifsc' => RequestMethods::post('account_code', ''),
                        'account_no' => RequestMethods::post('account_number', ''),
                        'account_owner' => RequestMethods::post('account_owner', '')
                    ];
                    $user->meta = $meta; $user->save();
                    $view->set('message', 'Bank Info Updated!!');
                    break;

                case 'payout':
                    $meta = $user->getMeta();
                    $meta['payout'] = [
                        'paypal' => RequestMethods::post('paypal', ''),
                        'paytm' => RequestMethods::post('paytm', '')
                    ];
                    $user->meta = $meta; $user->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                default:
                    break;
            }
            $this->setUser($user);
        }
        $view->set("publisher", $publisher);
    }

    /**
     * @before _admin
     */
    public function delete($pid) {
        $this->JSONview();
        $view = $this->getActionView();

        if (RequestMethods::type() !== 'DELETE') {
            $this->redirect("/404");
        }

        $user = \User::first(["_id" => $pid, 'type' => 'publisher', 'org_id' => $this->org->_id]);
        if (!$user) {
            $this->redirect("/404");
        }
        $clicks = \Click::count(["pid = ?" => $user->_id]);
        if ($clicks === 0) {
            $query = ['user_id' => $user->_id];
            $user->delete();
            \Link::deleteAll($query);
            \Performance::deleteAll($query);

            $view->set('message', 'Publisher removed successfully!!');
        } else {
            $view->set('message', 'Failed to delete. Publisher has already given clicks!!');
        }
    }

    /**
     * @protected
     * @Over ride
     */
    public function _secure() {
        parent::_secure();
        if ($this->user->type !== 'publisher' || !$this->org) {
            $this->noview();
            throw new \Framework\Router\Exception\Controller("Invalid Request");
        }
        $this->setLayout("layouts/publisher");
    }

    /**
     * @before _session
     */
    public function register() {
        $this->seo(array("title" => "Publisher Register", "description" => "Register"));
        $view = $this->getActionView(); $view->set('errors', []);

        $csrf_token = $session->get('Publisher\Register:$token');
        $token = RequestMethods::post("token", '');
        if (RequestMethods::post("action") == "register" && $csrf_token && $token === $csrf_token) {
            $this->_publisherRegister($this->org, $view);
        }
        $csrf_token = Framework\StringMethods::uniqRandString(44);
        $session->set('Publisher\Register:$token', $csrf_token);
        $view->set('__token', $csrf_token);
        $view->set('organization', $this->org);
    }
    
}