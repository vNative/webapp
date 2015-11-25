<?php

/**
 * Description of content
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Content extends Member {

    /**
     * @before _secure, memberLayout
     */
    public function index() {
        $this->seo(array("title" => "Favourite Categories", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        $title = RequestMethods::get("title", "");
        $category = implode(",", RequestMethods::get("category"));
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 9);

        $where = array(
            "title LIKE ?" => "%{$title}%",
            "category LIKE ?" => "%{$category}%",
            "live = ?" => true
        );
        
        $items = Item::all($where, array("id", "title", "image", "url", "description"), "created", "desc", $limit, $page);
        $count = Item::count($where);

        $session = Registry::get("session");

        $view->set("limit", $limit);
        $view->set("title", $title);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("items", $items);
        $view->set("category", $category);
        $view->set("domains", $this->target());
    }
    
    /**
     * @before _secure, changeLayout
     */
    public function create() {
        $this->seo(array("title" => "Create Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        if (RequestMethods::post("action") == "content") {
            $item = new Item(array(
                "url" =>  RequestMethods::post("url"),
                "title" => RequestMethods::post("title"),
                "image" => $this->_upload("image", "images"),
                "commission" => RequestMethods::post("commission", "4.99"),
                "category" => implode(",", RequestMethods::post("category", "")),
                "description" => RequestMethods::post("description"),
                "user_id" => $this->user->id
            ));
            $item->save();
            
            $rpms = RequestMethods::post("rpm");
            foreach ($rpms as $key => $value) {
                $rpm = new RPM(array(
                    "item_id" => $item->id,
                    "value" => $value,
                    "country" => $key
                ));
                $rpm->save();
            }
            $view->set("success", true);
        }
    }
    
    protected function target() {
        $session = Registry::get("session");
        $domains = $session->get("domains");

        $alias = array();
        foreach ($domains as $domain) {
            array_push($alias, $domain->value);
        }
        
        return $alias;
    }
    
    /**
     * @before _secure, changeLayout
     */
    public function all() {
        $this->seo(array("title" => "Manage Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $website = RequestMethods::get("website", "");
        $startdate = RequestMethods::get("startdate", date('Y-m-d', strtotime("-7 day")));
        $enddate = RequestMethods::get("enddate", date('Y-m-d', strtotime("now")));

        $where = array(
            "url LIKE ?" => "%{$website}%",
            "created >= ?" => $this->changeDate($startdate, "-1"),
            "created <= ?" => $this->changeDate($enddate, "1")
        );
        
        $contents = Item::all($where, array("id", "title", "created", "url", "live"), "created", "desc", $limit, $page);
        $count = Item::count($where);

        $view->set("contents", $contents);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
    }
    
    /**
     * @before _secure, changeLayout
     */
    public function platforms() {
        $this->seo(array("title" => "New User Platforms", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        $date = RequestMethods::get("date", date('Y-m-d', strtotime("now")));
        $live = RequestMethods::get("live", 0);
        $users = User::all(array("live = ?" => $live, "created LIKE ?" => "%{$date}%"), array("id","name", "created"));
            
        $view->set("users", $users);
        $view->set("date", $date);
    }
    
    /**
     * @before _secure, changeLayout
     */
    public function edit($id = NULL) {
        $this->seo(array("title" => "Edit Content", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $item = Item::first(array("id = ?" => $id));
        $rpm_in = RPM::first(array("item_id = ?" => $item->id, "country = ?" => "IN"));
        $rpm_us = RPM::first(array("item_id = ?" => $item->id, "country = ?" => "US"));
        $rpm_pk = RPM::first(array("item_id = ?" => $item->id, "country = ?" => "PK"));
        $rpm_au = RPM::first(array("item_id = ?" => $item->id, "country = ?" => "AU"));
        $rpm_nw = RPM::first(array("item_id = ?" => $item->id, "country = ?" => "NW"));
        $rpm_none = RPM::first(array("item_id = ?" => $item->id, "country = ?" => "NONE"));
        
        if (RequestMethods::post("action") == "update") {
            $item->title = RequestMethods::post("title");
            $item->url = RequestMethods::post("url");
            $item->commission = RequestMethods::post("commission");
            $item->category = implode(",", RequestMethods::post("category"));
            $item->description = RequestMethods::post("description");
            $item->live = RequestMethods::post("live", "0");
            
            $item->save();

            $rpm_in->value = RequestMethods::post("rpm_in");
            $rpm_in->save();
            $rpm_us->value = RequestMethods::post("rpm_us");
            $rpm_us->save();
            $rpm_ca->value = RequestMethods::post("rpm_ca");
            $rpm_ca->save();
            $rpm_au->value = RequestMethods::post("rpm_au");
            $rpm_au->save();
            $rpm_uk->value = RequestMethods::post("rpm_uk");
            $rpm_uk->save();
            $rpm_none->value = RequestMethods::post("rpm_none");
            $rpm_none->save();

            $view->set("success", true);

            $view->set("errors", $item->getErrors());
        }
        $view->set("item", $item);
        $view->set("rpm_in", $rpm_in);
        $view->set("rpm_us", $rpm_us);
        $view->set("rpm_pk", $rpm_pk);
        $view->set("rpm_au", $rpm_au);
        $view->set("rpm_nw", $rpm_nw);
        $view->set("rpm_none", $rpm_none);
        $view->set("categories", explode(",", $item->category));
    }
    
    /**
     * @before _secure, changeLayout
     */
    public function shortenURL() {
        $this->seo(array("title" => "Shorten URL", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        if (RequestMethods::get("longURL")) {
            $longURL = RequestMethods::get("longURL");
            $googl = Registry::get("googl");
            $object = $googl->shortenURL($longURL);
            
            $view->set("shortURL", $object->id);
            $view->set("googl", $object);
        }
    }

    /**
     * @before _secure, changeLayout
     */
    public function fraudLinks() {
        $this->seo(array("title" => "Fraud Links", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        if (RequestMethods::get("longURL")) {
            $longURL = RequestMethods::get("longURL");
            $googl = Registry::get("googl");
            $object = $googl->shortenURL($longURL);
            
            $view->set("shortURL", $object->id);
            $view->set("googl", $object);
        }
    }

    /**
     * @before _secure, changeLayout
     */
    public function domains() {
        $this->seo(array("title" => "All Domains", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $domains = Meta::all(array("property = ?" => "domain"));

        if (RequestMethods::get("domain")) {
            $exist = Meta::first(array("property" => "domain", "value = ?" => RequestMethods::get("domain")));
            if($exist) {
                $view->set("message", "Domain Exists");
            } else {
                $domain = new Meta(array(
                    "user_id" => $this->user->id,
                    "property" => "domain",
                    "value" => RequestMethods::get("domain")
                ));
                $domain->save();
                array_push($domains, $domain);
                $view->set("message", "Domain Added Successfully");
            }
        }

        $view->set("domains", $domains);
    }

    /**
     * @before _secure, changeLayout
     */
    public function delete($id = NULL) {
        $this->noview();
        $item = Item::first(array("id = ?" => $id));

        $earnings = Earning::all(array("item_id = ?" => $item->id));
        foreach ($earnings as $earning) {
            $earning->delete();
        }

        $links = Link::all(array("item_id = ?" => $item->id));
        foreach ($links as $link) {
            $stat = Stat::all(array("link_id = ?" => $link->id));
            $stat->delete();
            $link->delete();
        }

        $rpms = RPM::all(array("item_id = ?" => $item->id));
        foreach ($rpms as $rpm) {
            $rpm->delete();
        }

        $item->delete();
        self::redirect($_SERVER["HTTP_REFERER"]);        
    }

    public function resize($image, $width = 470, $height = 246) {
        $path = APP_PATH . "/public/assets/uploads/images";
        $cdn = CDN;$image = base64_decode($image);
        if ($image) {
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $extension = pathinfo($image, PATHINFO_EXTENSION);

            if ($filename && $extension) {
                $thumbnail = "{$filename}-{$width}x{$height}.{$extension}";
                if (!file_exists("{$path}/{$thumbnail}")) {
                    $imagine = new \Imagine\Gd\Imagine();
                    $size = new \Imagine\Image\Box($width, $height);
                    $mode = Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
                    $imagine->open("{$path}/{$image}")->thumbnail($size, $mode)->save("{$path}/resize/{$thumbnail}");
                }
                header("Location: {$cdn}uploads/images/resize/{$thumbnail}");
                exit();
            }
            header("Location: /images/{$image}");
            exit();
        } else {
            header("Location: {$cdn}img/logo.png");
            exit();
        }
    }

}