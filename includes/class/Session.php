<?php
    /**
     * Class: Session
     * Handles their session.
     */
    class Session implements SessionHandlerInterface {
        # Variable: $data
        # Caches session data.
        public static $data = "";

        public function __construct() {
            $domain = '';
            $host = $_SERVER['HTTP_HOST'];
            if (is_numeric(str_replace('.', '', $host)))
                $domain = $host;
            elseif (count(explode('.', $host)) >= 2)
                $domain = preg_replace("/^www\./", '.', $host);

            session_set_cookie_params(60 * 60 * 24 * 30, '/', $domain);
            session_name('ChyrpSession');
        }

        /**
         * Function: open
         * Returns: @true@
         */
        public function open($path, $name) {
            return true;
        }

        /**
         * Function: close
         * Returns: @true@
         */
        public function close() {
            return true;
        }

        /**
         * Function: read
         * Reads their session from the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        public function read($id) {
            self::$data = SQL::current()->select("sessions",
                                                 "data",
                                                 array("id" => $id),
                                                 "id")->fetchColumn();

            return fallback(self::$data, "");
        }

        /**
         * Function: write
         * Writes their session to the database, or updates it if it already exists.
         *
         * Parameters:
         *     $id - Session ID.
         *     $data - Data to write.
         */
        public function write($id, $data) {
            if (empty($data))
                return false;

            if ($data === self::$data)
                return true;

            $sql = SQL::current();

            if ($sql->count("sessions", array("id" => $id)))
                $sql->update("sessions",
                             array("id" => $id),
                             array("data" => $data,
                                   "user_id" => Visitor::current()->id,
                                   "updated_at" => datetime()));
            else
                $sql->insert("sessions",
                             array("id" => $id,
                                   "data" => $data,
                                   "user_id" => Visitor::current()->id,
                                   "created_at" => datetime()));

            return true;
        }

        /**
         * Function: destroy
         * Destroys their session.
         *
         * Parameters:
         *     $id - Session ID.
         */
        public function destroy($id) {
            if (SQL::current()->delete("sessions", array("id" => $id)))
                return true;

            return false;
        }

        /**
         * Function: gc
         * Garbage collector. Removes sessions older than 30 days and sessions with no stored data.
         */
        public function gc($max_lifetime) {
            SQL::current()->delete("sessions",
                                   "created_at <= :thirty_days OR data = '' OR data IS NULL",
                                   array(":thirty_days" => datetime(strtotime("-30 days"))));
            return true;
        }
    }
