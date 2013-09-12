<?php /*@charset "utf-8"*/
/**
 *********************************************************************
 * プラグインベースクラス ver1.0
 * @file   libpw_plugin.php
 * @date   2013-03-12 20:51:26 (Tuesday)
 *********************************************************************/

if(!class_exists('libpw_Plugin_Substance')){
    /**
     *====================================================================
     * ルートクラス
     *===================================================================*/
    abstract class libpw_Plugin_Root_Abstract {
        abstract protected function __construct($unique);

        public function init() {
            // 派生クラスで実装
        }
    }

    class libpw_Plugin_Root extends libpw_Plugin_Root_Abstract {
        protected $unique = '';
        public function __construct($unique) {
            $this->unique = $unique;
        }
    }

    class libpw_Plugin_SingletonRoot extends libpw_Plugin_Root_Abstract {
        protected $unique = '';
        protected function __construct($unique) {
            $this->unique = $unique;
        }
    }

    /**
     *====================================================================
     * データ保存クラス
     *===================================================================*/
    class libpw_Plugin_DataStore extends libpw_Plugin_Root {
        private $value;
        private $default;
        private $counter;
        private $key;

        const CIPHER = 'rijndael-128';
        const MODE   = 'cbc';

        public function __construct($unique, $default = array(), $key = null) {
            parent::__construct($unique);
            $this->value   = $default;
            $this->default = $default;
            $this->counter = 0;
            if(is_null($key) || $key == ''){
                $this->key = $this->unique;
            }else{
                $this->key = $key;
            }
        }

        public function init() {
            $this->value = $this->default;
        }

        public function set($key, $val) {
            $this->value[$key] = $val;
        }

        public function get($key) {
            return $this->value[$key];
        }

        public function getAll() {
            return $this->value;
        }

        public function eq($key, $val) {
            if(isset($this->value[$key]) && ($this->value[$key] == $val)){
                return true;
            }
            return false;
        }

        public function update($list = array()) {
            foreach($list as $key => $val){
                if(isset($this->default[$key])){
                    $this->set($key, $val);
                }
            }
        }

        public function load() {
            if($this->counter == 0){
                if(!$this->value = $this->decrypt(get_option($this->unique))){
                    $this->init();
                }
            }
            ++$this->counter;
        }

        public function save() {
            if(get_option($this->unique) === false){
                add_option($this->unique, $this->encrypt($this->value), '', 'no');
            }else{
                update_option($this->unique, $this->encrypt($this->value));
            }
        }

        public function clear() {
            $this->counter--;
            if($this->counter <= 0){
                $this->value = array();
            }
        }

        public function delete() {
            delete_option($this->unique);
        }

        private function encrypt($val) {
            $val = serialize($val);
            // mcryptで暗号化の準備
            $iv_size  = mcrypt_get_iv_size(self::CIPHER, self::MODE);
            $iv       = mcrypt_create_iv($iv_size, MCRYPT_DEV_URANDOM);
            //$key      = substr($this->unique, 0, $iv_size);
            $key      = substr($this->key, 0, $iv_size);
            $dummy_iv = str_pad($key, $iv_size, $key);

            // msg暗号化
            $cval = mcrypt_encrypt(self::CIPHER, $key, base64_encode($val), self::MODE, $iv);

            // 初期化ベクトル暗号化
            $civ = mcrypt_encrypt(self::CIPHER, $key, base64_encode($iv), self::MODE, $dummy_iv);

            return serialize(array(base64_encode($cval), base64_encode($civ)));
        }

        private function decrypt($val) {
            if(!$val){
                return $val;
            }
            if(is_array($val)){
                $crypt = $val;
            }else{
                $crypt = unserialize($val);
            }
            $cval     = base64_decode($crypt[0]);
            $civ      = base64_decode($crypt[1]);
            $iv_size  = mcrypt_get_iv_size(self::CIPHER, self::MODE);
            //$key      = substr($this->unique, 0, $iv_size);
            $key      = substr($this->key, 0, $iv_size);
            $dummy_iv = str_pad($key, $iv_size, $key);
            $iv       = base64_decode(rtrim(mcrypt_decrypt(self::CIPHER, $key, $civ, self::MODE, $dummy_iv), "\0"));
            return unserialize(base64_decode(rtrim(mcrypt_decrypt(self::CIPHER, $key, $cval, self::MODE, $iv), "\0")));
        }
    }

    /**
     *====================================================================
     * HTTPリクエスト
     *===================================================================*/
    class libpw_Plugin_HttpRequest extends libpw_Plugin_DataStore {
        public function __construct($unique) {
            parent::__construct($unique, $_REQUEST);
        }

        public function isUpdate() {
            return $this->eq($this->unique . '_UPDATE', 'yes');
        }
    }

    /**
     *====================================================================
     * プラグイン
     *===================================================================*/
    class libpw_Plugin_Substance extends libpw_Plugin_SingletonRoot {
        protected $request;
        protected $page_title;
        protected $menu_title;
        protected $user_level;
        protected $path;
        protected $class;
        static protected $substance = array();

        protected function __construct($unique, $class, $path) {
            parent::__construct($unique);
            $this->path    = $path;
            $this->class   = $class;
            $this->request = new libpw_Plugin_HttpRequest($unique);
            $this->init();
            register_activation_hook($path, array(&$this, 'activate'));
            register_deactivation_hook($path, array(&$this, 'deactivate'));
            register_uninstall_hook($path, array($class , 'uninstall'));
        }

        static public function create($unique, $class, $path) {
            // PHP5.3+で new static 式に切り替える
            if(!isset(self::$substance[$class])){
                self::$substance[$class] = new $class($unique, $class, $path);
            }
        }

        static protected function & getInstance($class) {
            return self::$substance[$class];
        }

        final public function __clone(){
            throw new Exception("can't clone this object");
        }

        public function activate() {
            // プラグインを有効化したときのアクションフックです
            // このメソッドは各継承クラスにて実装してください
        }

        public function deactivate() {
            // プラグインを停止するときのアクションフックです
            // このメソッドは各継承クラスにて実装してください
        }

        static public function uninstall() {
            // プラグインを削除するときのアクションフックです
            // このメソッドは各継承クラスにて実装してください
        }

        public function addMenu($page_title, $menu_title, $user_level = 'level_8') {
            $this->page_title = $page_title;
            $this->menu_title = $menu_title;
            $this->user_level = $user_level;
            add_action('admin_menu', array(&$this, 'execMenu'));
        }

        public function execMenu() {
            add_options_page(
                $this->page_title,
                $this->menu_title,
                $this->user_level,
                basename($this->path),
                array(&$this, 'render')
                );
        }

        public function render() {
            // 画面表示クラスです
            // このメソッドは各継承クラスにて実装してください
        }

        //---------------------------------------------------------------------
        // 以下はrender補助メソッド
        protected function renderWrapStart() {
            echo '<div class="wrap">';
        }

        protected function renderWrapEnd() {
            echo '</div>';
        }

        protected function renderHeadding($h = null) {
            if(is_null($h)){
                $h = $this->unique;
            }
            echo '<h2>' . $h . '</h2>';
        }

        protected function renderFormStart() {
            $update = $this->unique . '_UPDATE';
            echo '<form method="post"><input type="hidden" name="' . $update . '" value="yes" />';
        }

        protected function renderFormEnd() {
            echo '</from>';
        }

        protected function renderTableStart() {
            echo '<table class="form-table">';
        }

        protected function renderTableEnd() {
            echo '</table>';
        }

        protected function renderTrStart() {
            echo '<tr valign="top">';
        }

        protected function renderTrEnd() {
            echo '</tr>';
        }

        protected function renderTh($str, $rowspan = 1, $colspan = 1) {
            echo "<th scope=\"row\" rowspan=\"$rowspan\" colspan=\"$colspan\">$str</th>";
        }

        protected function renderTd($str, $rowspan = 1, $colspan = 1) {
            echo "<td rowspan=\"$rowspan\" colspan=\"$colspan\">$str</td>";
        }

        protected function renderTdStart($rowspan = 1, $colspan = 1) {
            echo "<td rowspan=\"$rowspan\" colspan=\"$colspan\">";
        }

        protected function renderTdEnd() {
            echo '</td>';
        }

        protected function renderTableNode($th, $td, $r1 = 1, $c1 = 1, $r2 = 1, $c2 = 1) {
            $this->renderTrStart();
            $this->renderTh($th, $r1, $c1);
            $this->renderTd($td, $r2, $c2);
            $this->renderTrEnd();
        }

        protected function renderTableLine() {
            $this->renderTableEnd();
            echo '<hr style="margin:20px 5px 10px;border:1px solid #ccc;">';
            $this->renderTableStart();
        }

        protected function renderStart($h = null) {
            $this->renderWrapStart();
            $this->renderHeadding($h);
            $this->renderFormStart();
        }

        protected function renderEnd() {
            $this->renderFormEnd();
            $this->renderWrapEnd();
        }

        protected function renderSubmit($str = '変更を保存') {
            echo '<p class="submit"><input type="submit" class="button-primary" value="' . $str . '" /></p>';
        }

        protected function renderUpdate($str) {
            echo '<div id="message" class="updated below-h2">' . $str . '</div>';
        }
    }
}
