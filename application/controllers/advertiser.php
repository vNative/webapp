<?php
/**
 * @author Faizan Ayubi
 */
use Shared\{Utils, Mail};
use Shared\Services\Db as Db;
use Framework\{Registry, ArrayMethods, RequestMethods as RM};

class Advertiser extends Auth {

    /**
     * @before _secure
     */
    public function index() {
        $this->seo(array("title" => "Dashboard", "description" => "Stats for your Data"));
        $view = $this->getActionView();$commissions = [];

        $start = RM::get("start", date("Y-m-d", strtotime('now')));
        $end = RM::get("end", date("Y-m-d", strtotime('now')));
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime("-1 day"));
        $perf = Shared\Services\User::livePerf($this->user, $today, $today);
        $yestPerf = Shared\Services\User::livePerf($this->user, $yesterday, $yesterday);

        $notifications = Notification::all([
            "org_id = ?" => $this->org->id,
            "meta = ?" => ['$in' => ['all', $this->user->_id]]
        ], [], "created", "desc", 5, 1);
        
        $total = Performance::total(
            [
                'start' => date("Y-m-d", strtotime('-365 day')),
                'end' => date("Y-m-d", strtotime('-2 day'))
            ],
            $this->user
        );
        
        $view->set("start", date("Y-m-d", strtotime('-7 day')))
            ->set("end", date("Y-m-d", strtotime('now')))
            ->set("notifications", $notifications)
            ->set("total", $total)
            ->set("yestPerf", $yestPerf)
            ->set("performance", $perf);
    }

    /**
     * @before _secure
     */
    public function campaigns() {
        $this->seo(array("title" => "Campaigns"));$view = $this->getActionView();
        $start = RM::get("start", strftime("%Y-%m-%d", strtotime('-7 day')));
        $end = RM::get("end", strftime("%Y-%m-%d", strtotime('now')));

        $limit = RM::get("limit", 20);
        $page = RM::get("page", 1);

        $query = [ "user_id" => $this->user->id ];
        $ads = \Ad::all($query, ['title', 'image', 'category', '_id', 'live', 'created'], 'created', 'desc', $limit, $page);
        $count = \Ad::count($query);
        $in = Db::convertType(array_keys($ads));

        $query["created"] = Db::dateQuery($start, $end);
        $records = Db::query('Click', [
            'adid' => ['$in' => $in],
            'is_bot' => false,
            'created' => $query["created"]
        ], ['adid']);

        $view->set("ads", $ads);
        $view->set("start", $start);
        $view->set("end", $end);
        $view->set([
            'count' => $count, 'page' => $page,
            'limit' => $limit,
            'dateQuery' => $query['created'],
            'clicks' => Click::classify($records, 'adid')
        ]);
    }

    /**
     * @before _secure
     */
    public function campaign($id) {
        $ad = \Ad::first(['_id' => $id, 'org_id' => $this->org->_id]);
        if (!$ad) $this->_404();

        $this->seo(["title" => $ad->title]); $view = $this->getActionView();

        if (RM::post("action")) { // action value already checked in _postback func
            $this->_postback('add', ['ad' => $ad]);
        }

        if (RM::type() === 'DELETE') {
            $this->_postback('delete');
        }

        $start = RM::get("start", date('Y-m-d', strtotime("-7 day")));
        $end = RM::get("end", date('Y-m-d'));
        $limit = RM::get("limit", 10); $page = RM::get("page", 1);
        $query = [
            'adid' => Db::convertType($id),
            'created' => Db::dateQuery($start, $end)
        ];
        $clicks = \Click::all($query, [], 'created', 'desc', $limit, $page);
        $count = \Click::count($query);
        $cf = Utils::getConfig("cf", "cloudflare");
        $view->set("domain", $cf->api->domain)
            ->set("clicks", $clicks)
            ->set("count", $count)
            ->set('advertiser', $this->user);

        $comms = Commission::all(["ad_id = ?" => $id], ['model', 'coverage', 'revenue', 'description']);
        $models = ArrayMethods::arrayKeys($comms, 'model');
        
        $advertiser = User::first(["id = ?" => $ad->user_id], ['name']);
        $categories = \Category::all(["org_id = ?" => $this->org->_id], ['name', '_id']);

        $this->_postback('show', ['ad' => $ad]);
        $view->set("ad", $ad)
            ->set("comms", $comms)
            ->set("categories", $categories)
            ->set("advertiser", $advertiser)
            ->set('models', $models)
            ->set("start", $start)
            ->set("end", $end);
    }

    /**
     * @before _secure
     */
    public function account() {
        $this->seo(array("title" => "Account"));
        $view = $this->getActionView();

        $user = $this->user;
        if (RM::type() === 'POST') {
            $action = RM::post('action');
            switch ($action) {
                case 'account':
                    $fields = ['name', 'phone', 'currency'];
                    foreach ($fields as $f) {
                        $user->$f = RM::post($f);
                    }
                    $user->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RM::post('password');
                    $new = RM::post('npassword');
                    $view->set($user->updatePassword($old, $new));
                    break;

                default:
                    $this->_postback('add');
                    break;
            }
        }

        if (RM::type() === 'DELETE') {
            $this->_postback('delete');
        }
        $this->_postback('show');
    }

    /**
     * @before _secure
     */
    public function bills() {
        $this->seo(array("title" => "Bills")); $view = $this->getActionView();

        $start = RM::get("start", date("Y-m-d", strtotime('-7 day')));
        $end = RM::get("end", date("Y-m-d", strtotime('now')));

        $query = [
            'user_id = ?' => $this->user->_id,
            'created = ?' => Db::dateQuery($start, $end)
        ];

        $performances = \Performance::all($query, [], 'created', 'desc');
        $invoices = \Invoice::all(['user_id = ?' => $this->user->_id]);
        $view->set("performances", $performances)
            ->set("invoices", $invoices);

        $view->set("start", $start);
        $view->set("end", $end);
    }

    /**
     * @before _admin
     */
    public function add() {
        $this->seo(array("title" => "Add Advertiser")); $view = $this->getActionView();
        $pass = Shared\Utils::randomPass();
        $view->set("pass", $pass)->set("errors", []);
        
        if (RM::type() == 'POST') {
            $user = \User::addNew('advertiser', $this->org, $view);
            if (!$user) return;
            $user->meta = [
                'campaign' => [
                    'model' => RM::post('model'),
                    'rate' => $this->currency(RM::post('rate')),
                    'coverage' => ['ALL']
                ]
            ];
            $user->save();

            if (RM::post("notify") == "yes") {
                Mail::send([
                    'user' => $user,
                    'template' => 'advertReg',
                    'subject' => $this->org->name . 'Support',
                    'org' => $this->org
                ]);
            }

            $user->password = sha1($user->password);
            $user->live = 1;
            $user->save();
            $this->redirect("/advertiser/manage.html");
        }
    }

    /**
     * @before _admin
     */
    public function manage() {
        $this->seo(array("title" => "Manage")); $view = $this->getActionView();

        $page = RM::get("page", 1);
        $limit = RM::get("limit", 30);
        $query = ["type = ?" => "advertiser", "org_id = ?" => $this->org->_id];
        $property = RM::get("property", "live");
        $value = RM::get("value", 0);
        if (in_array($property, ["live", "id"])) {
            $query["{$property} = ?"] = $value;
        } else if (in_array($property, ["email", "name", "phone"])) {
            $query["{$property} = ?"] = Db::convertType($value, 'regex');
        }

        $advertisers = \User::all($query, ['_id', 'name', 'live', 'email', 'created'], 'created', 'desc');
        $count = \User::count($query);
        $active = \User::count(["type = ?" => "advertiser", "org_id = ?" => $this->org->_id, "live = ?" => 1]);
        $inactive = \User::count(["type = ?" => "advertiser", "org_id = ?" => $this->org->_id, "live = ?" => 0]);

        $view->set("advertisers", $advertisers)
            ->set("property", $property)
            ->set("value", $value)
            ->set("active", $active)
            ->set("inactive", $inactive)
            ->set("count", $count)
            ->set("limit", $limit)
            ->set("page", $page);
    }

    /**
     * @before _admin
     */
    public function info($id = null) {
        $this->seo(array("title" => "Advertiser Edit")); $view = $this->getActionView();

        $advertiser = User::first(["_id = ?" => $id, "type = ?" => "advertiser", "org_id = ?" => $this->org->id]);
        if (!$advertiser) $this->_404();

        $view->set("errors", []);
        if (RM::type() == 'POST') {
            $action = RM::post('action', '');
            switch ($action) {
                case 'account':
                    $fields = ['name', 'phone', 'country', 'currency'];
                    foreach ($fields as $f) {
                        $advertiser->$f = RM::post($f, $advertiser->$f);
                    }
                    $advertiser->save();
                    $view->set('message', 'Account Info updated!!');
                    break;

                case 'password':
                    $old = RM::post('password');
                    $new = RM::post('npassword');
                    $view->set($advertiser->updatePassword($old, $new));
                    break;

                case 'campaign':
                    $advertiser->getMeta()['campaign'] = [
                        'model' => RM::post('model'),
                        'rate' => $this->currency(RM::post('rate'))
                    ];
                    $advertiser->save();
                    $view->set('message', 'Payout Info Updated!!');
                    break;
                
                default:
                    break;
            }
        }
        $view->set("advertiser", $advertiser);
    }

    /**
     * @before _admin
     */
    public function update($id) {
        $this->JSONView(); $view = $this->getActionView();
        $a = \User::first(["_id = ?" => $id, "org_id = ?" => $this->org->_id]);
        if (!$a || RM::type() !== 'POST') {
            return $view->set('message', 'Invalid Request!!');
        }

        $updateAble = ['live', 'name'];
        foreach ($_POST as $key => $value) {
            if (in_array($key, $updateAble)) {
                $a->$key = $value;   
            }
        }
        $a->save();
        $view->set('message', 'Updated successfully!!');
    }

    /**
     * @protected
     */
    public function _admin() {
        parent::_secure();
        if ($this->user->type !== 'admin' || !$this->org) {
            $this->_404();
        }
        $this->setLayout("layouts/admin");
    }

    /**
     * Returns data of clicks, impressions, payouts for publishers with custom date range
     * @before _secure
     */
    public function performance() {
        $this->JSONview(); $view = $this->getActionView();
        
        $start = RM::get("start", date("Y-m-d", strtotime('-7 day')));
        $end = RM::get("end", date("Y-m-d", strtotime('now')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        
        $find = Performance::overall($dateQuery, $this->user);
        $view->set($find);
    }

    /**
     * @before _secure
     */
    public function impressions() {
        $this->JSONview();
        $view = $this->getActionView();
    }

    /**
     * @before _admin
     */
    public function delete($pid) {
        parent::delete($pid); $view = $this->getActionView();
        
        $user = \User::first(["_id" => $pid, 'type' => 'advertiser', 'org_id' => $this->org->_id]);
        if (!$user) $this->_404();

        $result = $user->delete();
        if ($result) {
            $view->set('message', 'Advertiser Deleted successfully!!');
        } else {
            $view->set('message', 'Failed to delete the advetiser data from database!!');   
        }
    }

    /**
     * @before _secure
     */
    public function platforms() {
        $this->seo(array("title" => "List of Platforms")); $view = $this->getActionView();

        if (RM::type() === 'POST') {
            $pid = RM::post('pid');
            try {
                if ($pid) {
                    $p = \Platform::first(['_id = ?' => $pid]);
                } else {
                    $p = new \Platform([
                        'user_id' => $this->user->_id,
                        'live' => true
                    ]);
                }
                $p->url = RM::post('url');
                $p->save();
                $view->set('message', 'Platform saved successfully!!');
            } catch (\Exception $e) {
                $view->set('message', $e->getMessage());
            }
        }

        $platforms = \Platform::all(["user_id = ?" => $this->user->_id], ['_id', 'url']);
        $results = [];

        $start = RM::get("start", date('Y-m-d', strtotime('-7 day')));
        $end = RM::get("end", date('Y-m-d', strtotime('-1 day')));
        $dateQuery = Utils::dateQuery(['start' => $start, 'end' => $end]);
        foreach ($platforms as $p) {
            $key = Utils::getMongoID($p->_id);
        }

        $view->set("platforms", $results)
            ->set("start", $start)
            ->set("end", $end);
    }

    /**
     * @protected
     * @Over ride
     */
    public function _secure() {
        parent::_secure();
        if ($this->user->type !== 'advertiser' || !$this->org) {
            $this->_404();
        }
        $this->setLayout("layouts/advertiser");
    }

    /**
     * @before _session
     * @after _csrfToken
     */
    public function register() {
        $this->seo(array("title" => "Advertiser Register", "description" => "Register"));
        $view = $this->getActionView();

        $view->set('errors', []);
        $token = RM::post("token", '');
        if (RM::post("action") == "register" && $this->verifyToken($token)) {
            $this->_advertiserRegister($this->org, $view);
        }
    }
}