<?php
    /**
     * Class: Comment
     * The model for the Comments SQL table.
     *
     * See Also:
     *     <Model>
     */
    class Comment extends Model {
        public $no_results = false;

        public $belongs_to = array("post", "user");

        /**
         * Function: __construct
         * See Also:
         *     <Model::grab>
         */
        public function __construct($comment_id, $options = array()) {
            parent::grab($this, $comment_id, $options);

            if ($this->no_results)
                return false;

            $this->body_unfiltered = $this->body;
            $group = ($this->user_id and !$this->user->no_results) ?
                         $this->user->group :
                         new Group(Config::current()->guest_group) ;

            $this->filtered = !isset($options["filter"]) or $options["filter"];

            $trigger = Trigger::current();

            $trigger->filter($this, "comment");

            if ($this->filtered) {
                if (($this->status != "pingback" and $this->status != "trackback") and !$group->can("code_in_comments"))
                    $this->body = strip_tags($this->body, "<".join("><", Config::current()->allowed_comment_html).">");

                $this->body_unfiltered = $this->body;

                $trigger->filter($this->body, array("markup_text", "markup_comment_text"));

                $trigger->filter($this, "filter_comment");
            }
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            return parent::search(get_class(), $options, $options_for_object);
        }

        /**
         * Function: create
         * Attempts to create a comment using the passed information. If a Defensio API key is present, it will check it.
         *
         * Parameters:
         *     $author - The name of the commenter.
         *     $email - The commenter's email.
         *     $url - The commenter's website.
         *     $body - The comment.
         *     $post - The <Post> they're commenting on.
         *     $type - The type of comment. Optional, used for trackbacks/pingbacks.
         */
        static function create($author, $email, $url, $body, $post,$type = null,$notify=1) {
            if (!self::user_can($post->id) and !in_array($type, array("trackback", "pingback")))
                return;

            $config = Config::current();
            $route = Route::current();
            $visitor = Visitor::current();

            if (!$type) {
                $status = ($post->user_id == $visitor->id) ? "approved" : $config->default_comment_status ;
                $type = "comment";
            } else
                $status = $type;

            if (!empty($config->akismet_api_key)) {
                $akismet = new Akismet($config->url, $config->akismet_api_key);

                $akismet->setCommentAuthor($author);
                $akismet->setCommentAuthorEmail($email);
                $akismet->setCommentAuthorURL($url);
                $akismet->setCommentContent($body);
                $akismet->setPermalink($post->url());
                $akismet->setCommentType($type);
                $akismet->setReferrer($_SERVER['HTTP_REFERER']);
                $akismet->setUserIP($_SERVER['REMOTE_ADDR']);

                if ($akismet->isCommentSpam()) {
                    self::add($body,
                              $author,
                              $url,
                              $email,
                              $_SERVER['REMOTE_ADDR'],
                              $_SERVER['HTTP_USER_AGENT'],
                              "spam",
                              null,
                              null,
                              $post,
                              $visitor->id,
                              $notify);
                    error(__("Spam Comment"), __("Your comment has been marked as spam. It will have to be approved before it will show up.", "comments"));
                } else {
                    $comment = self::add($body,
                                         $author,
                                         $url,
                                         $email,
                                         $_SERVER['REMOTE_ADDR'],
                                         $_SERVER['HTTP_USER_AGENT'],
                                         $status,
                                         null,
                                         null,
                                         $post,
                                         $visitor->id,
                                         $notify);

                    fallback($_SESSION['comments'], array());
                    $_SESSION['comments'][] = $comment->id;

                    if (isset($_POST['ajax']))
                        exit("{ \"comment_id\": \"".$comment->id."\", \"comment_timestamp\": \"".$comment->created_at."\" }");

                    Flash::notice(__("Comment added."), $post->url()."#comment_".$comment->id);
                }
            } else {
                $comment = self::add($body,
                                     $author,
                                     $url,
                                     $email,
                                     $_SERVER['REMOTE_ADDR'],
                                     $_SERVER['HTTP_USER_AGENT'],
                                     $status,
                                     null,
                                     null,
                                     $post,
                                     $visitor->id);

                fallback($_SESSION['comments'], array());
                $_SESSION['comments'][] = $comment->id;

                if (isset($_POST['ajax']))
                    exit("{ \"comment_id\": \"".$comment->id."\", \"comment_timestamp\": \"".$comment->created_at."\" }");

                Flash::notice(__("Comment added."), $post->url()."#comment_".$comment->id);
            }
        }

        /**
         * Function: add
         * Adds a comment to the database.
         *
         * Parameters:
         *     $body - The comment.
         *     $author - The name of the commenter.
         *     $url - The commenter's website.
         *     $email - The commenter's email.
         *     $ip - The commenter's IP address.
         *     $agent - The commenter's user agent.
         *     $status - The new comment's status.
         *     $created_at - The new comment's "created" timestamp.
         *     $updated_at - The new comment's "last updated" timestamp.
         *     $post - The <Post> they're commenting on.
         *     $user_id - The ID of this <User> this comment was made by.
         */
        static function add($body, $author, $url, $email, $ip, $agent, $status, $created_at = null, $updated_at = null, $post, $user_id,$notify) {
            if (!empty($url)) # Add the http:// if it isn't there.
                if (!@parse_url($url, PHP_URL_SCHEME))
                    $url = "http://".$url;

            $ip = ip2long($ip);
            if ($ip === false)
                $ip = 0;

            $sql = SQL::current();
            $sql->insert("comments",
                         array("body" => $body,
                               "author" => strip_tags($author),
                               "author_url" => strip_tags($url),
                               "author_email" => strip_tags($email),
                               "author_ip" => $ip,
                               "author_agent" => $agent,
                               "status" => $status,
                               "created_at" => oneof($created_at, datetime()),
                               "updated_at" => oneof($updated_at, "0000-00-00 00:00:00"),
                               "post_id" => $post->id,
                               "user_id"=> $user_id,
                               "notify"=>$notify));
            $new = new self($sql->latest("comments"));

            Trigger::current()->call("add_comment", $new);
            return $new;
        }

        public function update($author, $author_email, $author_url, $body, $status, $timestamp, $update_timestamp = true) {
            $sql = SQL::current();
            $sql->update("comments",
                         array("id" => $this->id),
                         array("author" => strip_tags($author),
                               "author_email" => strip_tags($author_email),
                               "author_url" => strip_tags($author_url),
                               "body" => $body,
                               "status" => $status,
                               "created_at" => $timestamp,
                               "updated_at" => ($update_timestamp) ? datetime() : $this->updated_at));

            Trigger::current()->call("update_comment", $this, $author, $author_email, $author_url, $body, $status, $timestamp, $update_timestamp);
        }

        static function delete($comment_id) {
            $trigger = Trigger::current();
            if ($trigger->exists("delete_comment"))
                $trigger->call("delete_comment", new self($comment_id));

            SQL::current()->delete("comments", array("id" => $comment_id));
        }

        public function editable($user = null) {
            fallback($user, Visitor::current());
            return ($user->group->can("edit_comment") or ($user->group->can("edit_own_comment") and $user->id == $this->user_id));
        }

        public function deletable($user = null) {
            fallback($user, Visitor::current());
            return ($user->group->can("delete_comment") or ($user->group->can("delete_own_comment") and $user->id == $this->user_id));
        }

        /**
         * Function: any_editable
         * Checks if the <Visitor> can edit any comments.
         */
        static function any_editable() {
            $visitor = Visitor::current();

            # Can they edit comments?
            if ($visitor->group->can("edit_comment"))
                return true;

            # Can they edit their own comments, and do they have any?
            if ($visitor->group->can("edit_own_comment") and
                SQL::current()->count("comments", array("user_id" => $visitor->id)))
                return true;

            return false;
        }

        /**
         * Function: any_deletable
         * Checks if the <Visitor> can delete any comments.
         */
        static function any_deletable() {
            $visitor = Visitor::current();

            # Can they delete comments?
            if ($visitor->group->can("delete_comment"))
                return true;

            # Can they delete their own comments, and do they have any?
            if ($visitor->group->can("delete_own_comment") and
                SQL::current()->count("comments", array("user_id" => $visitor->id)))
                return true;

            return false;
        }

        public function author_link() {
            if ($this->author_url != "") # If a URL is set
                return '<a href="'.$this->author_url.'">'.$this->author.'</a>';
            else # If not, just return their name
                return $this->author;
        }

        static function user_can($post) {
            $visitor = Visitor::current();
            if (!$visitor->group->can("add_comment")) return false;

            // assume allowed comments by default
            return empty($post->comment_status) or
                   !($post->comment_status == "closed" or
                    ($post->comment_status == "registered_only" and !logged_in()) or
                    ($post->comment_status == "private" and !$visitor->group->can("add_comment_private")));
        }

        static function user_count($user_id) {
            $count = SQL::current()->count("comments", array("user_id" => $user_id));
            return $count;
        }

        # !! DEPRECATED AFTER 2.0 !!
        public function post() {
            return new Post($this->post_id);
        }

        # !! DEPRECATED AFTER 2.0 !!
        public function user() {
            if ($this->user_id)
                return new User($this->user_id);
            else
                return false;
        }
    }
