<?php

/**
 * Subclass the Controller class within our application.
 *
 * @author Faizan Ayubi
 */

namespace Shared {

    use Framework\Events as Events;
    use Framework\Router as Router;
    use Framework\Registry as Registry;

    class Controller extends \Framework\Controller {

        /**
         * @readwrite
         */
        protected $_user;
        
        public function seo($params = array()) {
            $seo = Registry::get("seo");
            foreach ($params as $key => $value) {
                $property = "set" . ucfirst($key);
                $seo->$property($value);
            }
            $params["view"]->set("seo", $seo);
        }

        /**
         * @protected
         */
        public function _admin() {
            if (!$this->user->admin) {
                $this->setUser(false);
                throw new Router\Exception\Controller("Not a valid admin user account");
            }
        }

        /**
         * @protected
         */
        public function _secure() {
            $user = $this->getUser();
            if (!$user) {
                header("Location: /login.html");
                exit();
            }
        }

        /**
         * @protected
         */
        public function _session() {
            $user = $this->getUser();
            if ($user) {
                header("Location: /auth/account.html");
                exit();
            }
        }

        public static function redirect($url) {
            header("Location: {$url}");
            exit();
        }

        public function setUser($user) {
            $session = Registry::get("session");
            if ($user) {
                $session->set("user", $user->id);
            } else {
                $session->erase("user");
            }
            $this->_user = $user;
            return $this;
        }

        protected function array_sort($array, $on, $order=SORT_ASC) {
            $new_array = array();
            $sortable_array = array();

            if (count($array) > 0) {
                foreach ($array as $k => $v) {
                    if (is_array($v)) {
                        foreach ($v as $k2 => $v2) {
                            if ($k2 == $on) {
                                $sortable_array[$k] = $v2;
                            }
                        }
                    } else {
                        $sortable_array[$k] = $v;
                    }
                }

                switch ($order) {
                    case SORT_ASC:
                        asort($sortable_array);
                    break;
                    case SORT_DESC:
                        arsort($sortable_array);
                    break;
                }

                foreach ($sortable_array as $k => $v) {
                    $new_array[$k] = $array[$k];
                }
            }

            return $new_array;
        }

        protected function log($message = "") {
            $logfile = APP_PATH . "/logs/" . date("Y-m-d") . ".txt";
            $new = file_exists($logfile) ? false : true;
            if ($handle = fopen($logfile, 'a')) {
                $timestamp = strftime("%Y-%m-%d %H:%M:%S", time());
                $content = "[{$timestamp}]{$message}\n";
                fwrite($handle, $content);
                fclose($handle);
                if ($new) {
                    chmod($logfile, 0777);
                }
            }
        }

        protected function changeDate($date, $day) {
            return date_format(date_add(date_create($date),date_interval_create_from_date_string("{$day} day")), 'Y-m-d');;
        }
        
        public function logout() {
            $this->setUser(false);
            session_destroy();
            self::redirect("/index.html");
        }
        
        public function noview() {
            $this->willRenderLayoutView = false;
            $this->willRenderActionView = false;
        }

        public function JSONview() {
            $this->willRenderLayoutView = false;
            $this->defaultExtension = "json";
        }
        
        /**
         * The method checks whether a file has been uploaded. If it has, the method attempts to move the file to a permanent location.
         * @param string $name
         * @param string $type files or images
         */
        protected function _upload($name, $type = "images") {
            if (isset($_FILES[$name])) {
                $file = $_FILES[$name];
                $path = APP_PATH . "/public/assets/uploads/{$type}/";
                $extension = pathinfo($file["name"], PATHINFO_EXTENSION);
                $filename = uniqid() . ".{$extension}";
                if (move_uploaded_file($file["tmp_name"], $path . $filename)) {
                    return $filename;
                }
            }
            return FALSE;
        }

        /**
         * The Main Method to return Mailgun Instance
         * 
         * @return \Mailgun\Mailgun Instance of Mailgun
         */
        protected function mailgun() {
            $configuration = Registry::get("configuration");
            $parsed = $configuration->parse("configuration/mail");

            if (!empty($parsed->mail->mailgun) && !empty($parsed->mail->mailgun->key)) {
                $mg = new \Mailgun\Mailgun($parsed->mail->mailgun->key);
                return $mg;
            }
        }
        
        protected function getBody($options) {
            $template = $options["template"];
            $view = new \Framework\View(array(
                "file" => APP_PATH . "/application/views/layouts/email/{$template}.html"
            ));
            foreach ($options as $key => $value) {
                $view->set($key, $value);
            }

            return $view->render();
        }
        
        protected function notify($options) {
            $body = $this->getBody($options);
            $emails = isset($options["email"]) ? array($options["email"]) : array($options["user"]->email);
            $mailgun = $this->mailgun();
            $mailgun->sendMessage("clicks99.com",array(
                'from'    => 'Clicks99 Team <info@clicks99.com>',
                'to'      => $emails,
                'subject' => $options["subject"],
                'text'    => $body
            ));
            $this->log(implode(",", $emails));
        }

        public function __construct($options = array()) {
            parent::__construct($options);

            // connect to database
            $database = Registry::get("database");
            $database->connect();

            $mongoDB = Registry::get("MongoDB");
            if (!$mongoDB) {
                $mongo = new \MongoClient();
                $mongoDB = $mongo->selectDB("stats");
                Registry::set("MongoDB", $mongoDB);
            }

            // schedule: load user from session           
            Events::add("framework.router.beforehooks.before", function($name, $parameters) {
                $session = Registry::get("session");
                $controller = Registry::get("controller");
                $user = $session->get("user");
                if ($user) {
                    $controller->user = \User::first(array("id = ?" => $user));
                }
            });

            // schedule: save user to session
            Events::add("framework.router.afterhooks.after", function($name, $parameters) {
                $session = Registry::get("session");
                $controller = Registry::get("controller");
                if ($controller->user) {
                    $session->set("user", $controller->user->id);
                }
            });

            // schedule: disconnect from database
            Events::add("framework.controller.destruct.after", function($name) {
                $database = Registry::get("database");
                $database->disconnect();
            });
        }

        public function __destruct() {
            $view = $this->layoutView;
            if ($view && !$view->get('seo')) {
                $view->set('seo', \Framework\Registry::get("seo"));
            }
            parent::__destruct();
        }

        /**
         * Checks whether the user is set and then assign it to both the layout and action views.
         */
        public function render() {
            /* if the user and view(s) are defined, 
             * assign the user session to the view(s)
             */
            if ($this->user) {
                if ($this->actionView) {
                    $key = "user";
                    if ($this->actionView->get($key, false)) {
                        $key = "__user";
                    }
                    $this->actionView->set($key, $this->user);
                }
                if ($this->layoutView) {
                    $key = "user";
                    if ($this->layoutView->get($key, false)) {
                        $key = "__user";
                    }
                    $this->layoutView->set($key, $this->user);
                }
            }
            parent::render();
        }

    }

}
