<?php
/**
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class Manage extends Admin {

	/**
     * @before _secure, changeLayout, _admin
     */
    public function users() {
        $this->seo(array("title" => "New User Platforms", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 10);
        
        $property = RequestMethods::get("property", "live");
        $value = RequestMethods::get("value", false);

        $where = array("{$property} = ?" => $value);
        $users = User::all($where, array("id","name", "created", "live"), "created", "desc", $limit, $page);
        $count = User::count($where);

        $view->set("users", $users);
        $view->set("page", $page);
        $view->set("count", $count);
        $view->set("limit", $limit);
        $view->set("property", $property);
        $view->set("value", $value);
    }

	/**
     * @before _secure, changeLayout, _admin
     */
    public function fraud() {
        $this->seo(array("title" => "Fraud Links", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
    }

	/**
     * @before _secure, changeLayout, _admin
     */
    public function news() {
        $this->seo(array("title" => "Member News", "view" => $this->getLayoutView()));
        $view = $this->getActionView();
        if (RequestMethods::post("news")) {
            $news = new Meta(array(
                "user_id" => $this->user->id,
                "property" => "news",
                "value" => RequestMethods::post("news")
            ));
            $news->save();
            $view->set("message", "News Saved Successfully");
        }
        
        $allnews = Meta::all(array("property = ?" => "news"));
            
        $view->set("allnews", $allnews);
    }

    /**
     * @before _secure, _admin
     */
    public function delete($user_id) {
        $this->noview();
        $stats = Stat::first(array("user_id = ?" => $user_id));
        foreach ($stats as $stat) {
            $stat->delete();
        }

        $links = Link::all(array("user_id = ?" => $user_id));
        foreach ($links as $link) {
            $stat = Stat::first(array("link_id = ?" => $link->id));
            if ($stat) {
                $stat->delete();
            }
            $link->delete();
        }
        
        $platforms = Platform::all(array("user_id = ?" => $user_id));
        foreach ($platforms as $platform) {
            $platform->delete();
        }

        $account = Account::first(array("user_id = ?" => $user_id));
        if ($account) {
            $account->delete();
        }

        $transactions = Transaction::all(array("user_id = ?" => $user_id));
        foreach ($transactions as $transaction) {
            $transaction->delete();
        }

        $tickets = Ticket::all(array("user_id = ?" => $user_id));
        foreach ($tickets as $ticket) {
        	$conversations = Conversation::all(array("ticket_id = ?" => $ticket->id));
        	foreach ($conversations as $c) {
        		$c->delete();
        	}
            $ticket->delete();
        }

        $user = User::first(array("id = ?" => $user_id));
        if ($user) {
            $user->delete();
        }
        
        self::redirect($_SERVER["HTTP_REFERER"]);
    }
}