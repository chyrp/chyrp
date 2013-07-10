<?php
    class Audio extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "audio",
                                  "type" => "file",
                                  "label" => __("MP3 File", "audio"),
                                  "note" => "<small>(Max. file size: ".ini_get('upload_max_filesize').")</small>"));
            if (isset($_GET['action']) and $_GET['action'] == "bookmarklet")
                $this->setField(array("attr" => "from_url",
                                      "type" => "text",
                                      "label" => __("From URL?", "audio"),
                                      "optional" => true,
                                      "no_value" => true));
            $this->setField(array("attr" => "description",
                                  "type" => "text_block",
                                  "label" => __("Description", "audio"),
                                  "optional" => true,
                                  "preview" => true,
                                  "bookmarklet" => "selection"));

            $this->setFilter("description", array("markup_text", "markup_post_text"));

            $this->respondTo("delete_post", "delete_file");
            $this->respondTo("feed_item", "enclose_audio");
            $this->respondTo("filter_post", "filter_post");
            $this->respondTo("admin_write_post", "swfupload");
            $this->respondTo("admin_edit_post", "swfupload");
            $this->respondTo("post_options", "add_option");
        }

        public function swfupload($admin, $post = null) {
            if (isset($post) and $post->feather != "audio" or
                isset($_GET['feather']) and $_GET['feather'] != "audio")
                return;

            Trigger::current()->call("prepare_swfupload", "audio", "*.mp3;*.m4a;*.mp4;*.oga;*.ogg;*.webm");
        }

        public function submit() {
            if (!isset($_POST['filename'])) {
                if (isset($_FILES['audio']) and $_FILES['audio']['error'] == 0)
                    $filename = upload($_FILES['audio'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm"));
                elseif (!empty($_POST['from_url']))
                    $filename = upload_from_url($_POST['from_url'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm"));
                else
                    error(__("Error"), __("Couldn't upload audio file."));
            } else
                $filename = $_POST['filename'];

            return Post::add(array("filename" => $filename,
                                   "description" => $_POST['description']),
                             $_POST['slug'],
                             Post::check_url($_POST['slug']));
        }

        public function update($post) {
            if (!isset($_POST['filename']))
                if (isset($_FILES['audio']) and $_FILES['audio']['error'] == 0) {
                    $this->delete_file($post);
                    $filename = upload($_FILES['audio'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm"));
                } elseif (!empty($_POST['from_url'])) {
                    $this->delete_file($post);
                    $filename = upload_from_url($_POST['from_url'], array("mp3", "m4a", "mp4", "oga", "ogg", "webm"));
                } else
                    $filename = $post->filename;
            else {
                $this->delete_file($post);
                $filename = $_POST['filename'];
            }

            $post->update(array("filename" => $filename,
                                "description" => $_POST['description']));
        }

        public function title($post) {
            return oneof($post->title, $post->title_from_excerpt());
        }

        public function excerpt($post) {
            return $post->description;
        }

        public function feed_content($post) {
            return $post->description;
        }

        public function delete_file($post) {
            if ($post->feather != "audio") return;
            unlink(MAIN_DIR.Config::current()->uploads_path.$post->filename);
        }

        public function filter_post($post) {
            if ($post->feather != "audio") return;
            $post->audio_player = $this->audio_player($post->filename, array(), $post);
        }

        public function audio_type($filename) {
            $file_split = explode(".", $filename);
            $file_ext = strtolower(end($file_split));
            switch($file_ext) {
                case "mp3":
                    return "audio/mpeg";
                case "m4a":
                    return "audio/mp4";
                case "mp4":
                    return "audio/mp4";
                case "oga":
                    return "audio/ogg";
                case "ogg":
                    return "audio/ogg";
                case "webm":
                    return "audio/webm";
                default:
                    return "application/octet-stream";
            }
        }

        public function enclose_audio($post) {
            $config = Config::current();
            if ($post->feather != "audio" or !file_exists(uploaded($post->filename, false)))
                return;

            $length = filesize(uploaded($post->filename, false));

            echo '          <link rel="enclosure" href="'.uploaded($post->filename).'" type="'.$this->audio_type($post->filename).'" title="'.truncate(strip_tags($post->description)).'" length="'.$length.'" />'."\n";
        }

        public function audio_player($filename, $params = array(), $post) {
            $vars = "";
            foreach ($params as $name => $val)
                $vars.= "&amp;".$name."=".$val;

            $config = Config::current();

            $player = "\n".'<audio id="audio_with_controls_'.$post->id.'" class="audio_with_controls" controls>';
            $player.= "\n\t".'<source src="'.$config->chyrp_url.$config->uploads_path.$filename.$vars.'" type="'.$this->audio_type($filename).'" />';
            $player.= "\n\t".'<a href="'.$config->chyrp_url.$config->uploads_path.$filename.'" class="audio_fallback_link">'.fix($filename).'</a>';
            $player.= "\n".'</audio>';

            $player.= "\n".'<script>';
            $player.= "\n\t"."if (document.createElement('audio').canPlayType) { ";
            $player.= "if ( !document.createElement('audio').canPlayType('".$this->audio_type($filename)."') ) { ";
            $player.= "$('#audio_with_controls_".$post->id."').replaceWith('";
            $player.= '<a href="'.$config->chyrp_url.$config->uploads_path.$filename.'" class="audio_fallback_link">'.fix($filename).'</a>';
            $player.= "')";
            $player.= " } }";
            $player.= "\n".'</script>'."\n";

            return $player;
        }

        public function add_option($options, $post = null) {
            if (isset($post) and $post->feather != "audio") return;
            elseif (Route::current()->action == "write_post")
                if (!isset($_GET['feather']) and Config::current()->enabled_feathers[0] != "audio" or
                    isset($_GET['feather']) and $_GET['feather'] != "audio") return;

            $options[] = array("attr" => "from_url",
                               "label" => __("From URL?", "audio"),
                               "type" => "text");

            return $options;
        }
    }
