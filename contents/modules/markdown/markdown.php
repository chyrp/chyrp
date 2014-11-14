<?php
    require "lib/markdown.php";

    class Markdown extends Modules {
        public function __init() {
            $this->addAlias("markup_text", "markdownify", 8);
            $this->addAlias("preview", "markdownify", 8);
        }
        static function markdownify($text) {
            return Markdown($text);
        }

        public function admin_head()
        {
            if (!Config::current()->enable_wysiwyg) return;
            $action = Route::current()->action;

            $current_action = isset($action) ? $action : 'write_post';
            $wysiwyg_actions = array('write_post', 'edit_post', 'write_page', 'edit_page');

            if (!in_array($current_action, $wysiwyg_actions))
                return;
            ?>

            <!-- Markdown Editor -->
            <link rel="stylesheet" href="//cdn.jsdelivr.net/editor/0.1.0/editor.css">
            <script src="//cdn.jsdelivr.net/editor/0.1.0/editor.js"></script>
            <script src="//cdn.jsdelivr.net/editor/0.1.0/marked.js"></script>
            <link rel="stylesheet" href="//cdn.jsdelivr.net/editor/0.1.0/yue.css">
            <script type="text/javascript">
            <?php $this->editorJS(); ?>
            </script>
            <?php
        }

        public function editorJS()
        {
?>//<script>
            $(function() {
                var editor = new Editor();
                editor.render();
            });
<?php
        }
    }
