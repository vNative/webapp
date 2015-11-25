<?php

/**
 * Description of analytics
 *
 * @author Faizan Ayubi
 */
use Framework\Registry as Registry;
use Framework\RequestMethods as RequestMethods;
use \Curl\Curl;

class Analytics extends Admin {
    
    /**
     * @before _secure, changeLayout
     */
    public function googl() {
        $this->seo(array("title" => "shortURL Analytics", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        
        if (RequestMethods::get("shortURL")) {
            $shortURL = RequestMethods::get("shortURL");
            $googl = Registry::get("googl");
            $object = $googl->analyticsFull($shortURL);

            $longUrl = explode("?item=", $object->longUrl);
            if($longUrl) {
                $str = base64_decode($longUrl[1]);
                $datas = explode("&", $str);
                foreach ($datas as $data) {
                    $property = explode("=", $data);
                    $item[$property[0]] = $property[1];
                }
            }

            $view->set("shortURL", $shortURL);
            $view->set("googl", $object);
            $view->set("item", $item);
        }
    }

    /**
     * @before _secure, changeLayout
     */
    public function content($id='') {
        $this->seo(array("title" => "Content Analytics", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $item = Item::first(array("id = ?" => $id));

        $earn = 0;
        $earnings = Earning::all(array("item_id = ?" => $item->id), array("amount"));
        foreach ($earnings as $earning) {
            $earn += $earning->amount;
        }

        $links = Link::count(array("item_id = ?" => $item->id));
        $rpm = RPM::count(array("item_id = ?" => $item->id));

        $view->set("item", $item);
        $view->set("earn", $earn);
        $view->set("links", $links);
        $view->set("rpm", $rpm);
    }

    /**
     * @before _secure, changeLayout
     */
    public function urlDebugger() {
        $this->seo(array("title" => "URL Debugger", "view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $url = RequestMethods::get("urld", "http://likesbazar.in/");
        $metas = get_meta_tags($url);

        $facebook = new Curl();
        $facebook->get('https://api.facebook.com/method/links.getStats', array(
            'format' => 'json',
            'urls' => $url
        ));
        $facebook->setOpt(CURLOPT_ENCODING , 'gzip');
        $facebook->close();

        $twitter = new Curl();
        $twitter->get('https://cdn.api.twitter.com/1/urls/count.json', array(
            'url' => $url
        ));
        $twitter->setOpt(CURLOPT_ENCODING , 'gzip');
        $twitter->close();

        $view->set("url", $url);
        $view->set("metas", $metas);
        $view->set("facebook", array_values($facebook->response)[0]);
        $view->set("twitter", $twitter->response);
    }

    /**
     * Total Stats today from Google Server realtime
     * @before _secure
     */
    public function realtime() {
        $this->JSONview();
        $view = $this->getActionView();

        $shortURL = RequestMethods::get("shortURL");
        $earning = 0;$count = 0;
        $links = Link::all(array("user_id = ?" => $this->user->id, "created >= ?" => date('Y-m-d', strtotime("-3 day"))), array("short", "item_id"));
        foreach ($links as $link) {
            $country_count = 0;$nonverified_count = 0;$verified_count = 0;
            $stat = Link::findStats($link->short);
            $total_count = $stat->analytics->day->shortUrlClicks;
            if ($stat->analytics->day->shortUrlClicks) {
                $referrers = $stat->analytics->day->referrers;
                foreach ($referrers as $referer) {
                    if ($referer->id == 'likesbazar.in') {
                        $nonverified_count += $referer->count;
                    }
                }
                $verified_count = $total_count - $nonverified_count;
                $correct = 0.95;

                $countries = $stat->analytics->day->countries;

                $rpms = RPM::all(array("item_id = ?" => $link->item_id), array("value", "country"));
                $rpms_country = array();
                $rpms_value = array();

                foreach ($rpms as $rpm) {
                    $rpms_country[] = strtoupper($rpm->country);
                    $rpms_value[strtoupper($rpm->country)] = $rpm->value;
                }
                foreach ($countries as $country) {
                    if (in_array($country->id, $rpms_country)) {
                        $earning += $correct*($rpms_value[$country->id])*($country->count)/1000;
                        $country_count += $country->count;
                    }
                }
                if ($verified_count > $country_count) {
                    $earning += ($verified_count - $country_count)*$correct*($rpms_value["NONE"])/1000;
                }
            }

            $count += $verified_count;
        }

        $view->set("avgrpm", round(($earning*1000)/($count), 2));
        $view->set("earnings", round($earning, 2));
        $view->set("clicks", $count);
    }

    public function link() {
        $this->JSONview();
        $view = $this->getActionView();

        $shortURL = RequestMethods::get("shortURL");
        $earning = 0;$count = 0;$verified_count = 0;$country_count = 0;
        $link = Link::first(array("short = ?" => $shortURL), array("item_id", "short"));
        if ($link) {
            $stat = Link::findStats($link->short);
            $total_count = $stat->analytics->allTime->shortUrlClicks;
            if ($stat->analytics->allTime->shortUrlClicks) {
                $referrers = $stat->analytics->allTime->referrers;
                foreach ($referrers as $referer) {
                    if (strpos($referer->id,'facebook.com') !== false) {
                        $verified_count += $referer->count;
                    }
                }
                //$correct = $verified_count/$total_count;
                $correct = 1;

                $countries = $stat->analytics->allTime->countries;

                $rpms = RPM::all(array("item_id = ?" => $link->item_id), array("value", "country"));
                foreach ($rpms as $rpm) {
                    foreach ($countries as $country) {
                        if(strtoupper($rpm->country) == $country->id) {
                            $earning += $correct*($rpm->value)*($country->count)/1000;
                            $country_count += $country->count;
                        }
                    }
                    if ($rpm->country == "NONE") {
                        //$earning += ($verified_count - $country_count)*$correct*($rpm->value)/1000;
                        $earning += ($total_count - $country_count)*$correct*($rpm->value)/1000;
                    }
                }
                //$view->set("rpm", round(($earning*1000)/($verified_count), 2));
                $view->set("rpm", round(($earning*1000)/($total_count), 2));
                $view->set("rpms", $rpms);
            }
            $view->set("stat", $stat);
        }
        
        $view->set("earning", round($earning, 2));
        //$view->set("click", round($verified_count,2));
        $view->set("click", round($total_count,2));
        $view->set("link", $link);
    }
    
}