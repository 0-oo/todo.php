<?php
/**
 *  Todo.php   - A Simple Task Manager -
 *  @version   0.4.2
 *  @see       http://0-oo.net/sbox/php-tool-box/todo
 *  @copyright 2008-2016 dgbadmin@gmail.com
 *  @license   http://0-oo.net/MIT_license.txt (The MIT license)
 */
class Todo {
    /** 文字コード */
    const ENCODING = 'UTF-8';

    /** サーバに保存するファイル名の文字コード */
    const FILE_NAME_ENCODING = 'UTF-8';         //Linuxその1
    //const FILE_NAME_ENCODING = 'EUC-JP';      //Linuxその2
    //const FILE_NAME_ENCODING = 'Shift_JIS';   //Windows

    /** TODOデータを保存するディレクトリ */
    const DATA_DIR = 'data';

    /** カテゴリ名として許可する正規表現 */
    const CAT_REGEX = '^[^\\\\./:*?"<>|]{1,20}$';

    /** バックアップの保存期限 */
    const BACKUP_TIME = '-7 day';

    /** 優先度の最大値 */
    const PRI_MAX = 5;

    public $cat;
    public $cats;
    public $list;

    /**
     *  コンストラクタ
     *  @param  string  $cat    カテゴリ
     */
    public function __construct($cat) {
        mb_internal_encoding(Todo::ENCODING);
        mb_regex_encoding(Todo::ENCODING);
        ini_set('default_charset', Todo::ENCODING); //HTTPヘッダーでの文字コード指定
        ini_set('mbstring.strict_detection', true);
        mb_substitute_character(0x005f);    //変換できない文字は"_"にする
        
        $this->cat = $this->_encode($cat);
    }
    /**
     *  表示の準備
     */
    public function setUp() {
        if ($this->isValidCat()) {
            if ($_REQUEST['delete'] && $this->_deleteCat()) {
            } else {
                if ($_POST['update']) {
                    $this->_updateList();
                }
                $this->list = $this->_getList();
            }
        }
        $this->cats = $this->_getCategories();
    }
    /**
     *  カテゴリチェック
     *  @return boolean 許可されるカテゴリかどうか
     */
    public function isValidCat() {
        return mb_eregi(Todo::CAT_REGEX, $this->cat);
    }
    /**
     *  TODOリストのファイルパスを取得する
     *  @return string  パス
     */
    public function getPath() {
        $cat = mb_convert_encoding($this->cat, Todo::FILE_NAME_ENCODING);
        return Todo::DATA_DIR . '/' . $cat . '.txt';
    }
    /**
     *  入力データの文字コードを正しく変換する
     *  @param  $input  string  入力データ
     *  @return string  文字コード変換後の入力データ
     */
    private function _encode($input) {
        return mb_convert_encoding($input, Todo::ENCODING);
    }
    /**
     *  カテゴリを全て取得する
     *  @return array   全てのカテゴリのカテゴリ名とファイルサイズ
     */
    private function _getCategories() {
        $h = openDir(Todo::DATA_DIR);
        if (!$h) {
            exit('data directory is not found.');
        }
        $cats = array();
        $limit = date('YmdHis', strToTime(Todo::BACKUP_TIME));
        while (false !== ($file = readDir($h))) {
            if (is_dir($file)) {
                continue;
            }
            $arr = explode('.', $file);
            $path = Todo::DATA_DIR . '/' . $file;
            if (count($arr) == 3) {        //バックアップの場合
                if ($arr[2] < $limit) {    //期限切れは削除
                    unlink($path);
                }
            } else {    //最新版の場合
                $cat = mb_convert_encoding($arr[0], Todo::ENCODING, Todo::FILE_NAME_ENCODING);
                $cats[$cat] = fileSize($path);
            }
        }
        closeDir($h);
        ksort($cats);
        return $cats;
    }
    /**
     *  TODOリストを更新して保存する
     */
    private function _updateList() {
        $oldCat = new Todo($_POST['oldcat']);   //変更前のカテゴリ
        $newPath = $this->getPath();
        if (is_file(strToUpper(__FILE__))) {    //ファイルパスで大文字小文字を区別しない場合
            $change = strCaseCmp($oldCat->cat, $this->cat);
        } else {
            $change = ($oldCat->cat != $this->cat);
        }
        if ($change && is_file($newPath)) {
            return;    //変更後のカテゴリが既に存在する場合は更新しない
        }

        $oldPath = $oldCat->getPath();
        if ($oldCat->isValidCat() && is_file($oldPath)) {
            rename($oldPath, $oldPath . '.' . date('YmdHis'));    //バックアップ
        }

        foreach ($_POST['todo'] as $post) {
            if ($post[1] != '') {    //TODO未入力は削除
                $data .= implode("\t", array_map(array($this, '_encode'), $post)) . "\n";
            }
        }
        file_put_contents($newPath, $data);
    }
    /**
     *  TODOリストを取得する
     *  @return array   TODOリスト
     */
    private function _getList() {
        $path = $this->getPath();
        if (!is_file($path)) {    //新規の場合
            return array('');
        }
        $list = explode("\n", file_get_contents($path));
        rsort($list);    //優先度順でソート
        return $list;
    }
    /**
     *  カテゴリを削除する
     *  @return boolean 削除できたかどうか
     */
    private function _deleteCat() {
        $path = $this->getPath();
        if (!is_file($path) || fileSize($path)) {    //todoが残っている場合は削除させない
            return false;
        }
        $this->cat = null;
        return unlink($path);
    }
}

//----- HTMLレンダリング用のグローバル関数 -----
/**
 *  HTMLエスケープ
 *  @param  string  $val    エスケープしたい文字列
 *  @return string          エスケープした文字列
 */
function h($val) {
    return htmlSpecialChars($val, ENT_QUOTES, Todo::ENCODING);
}
/**
 *  option要素を出力する
 *  @param  string  $val        optionの値
 *  @param  string  $selected   selectedにすべき値
 */
function echoOption($val, $selectedVal) {
    if ($val == $selectedVal) {
        $selected = 'selected="selected"';
    }
    echo "<option $selected>$val</option>\n";
}
//----------------------------------------------


$todo = new Todo($_REQUEST['cat']);
$todo->setUp();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="<?php echo Todo::ENCODING ?>" />
<title>TODO - <?php echo h($todo->cat) ?></title>
<link rel="stylesheet" href="//cdn.jsdelivr.net/jquery.ui/latest/jquery-ui.min.css" />
<link rel="stylesheet" href="//0-oo.github.io/pryn.css" />
<link rel="stylesheet" href="//0-oo.github.io/yahho-sticky-footer.css" />
<style>
#hd {           padding-top: 1em }
#bd {           font-size: 100% }
#cat {          margin-bottom: 1em; font-size: 161.6% }
h1, select, input { margin: 0 }
a {             text-decoration: none }
a:visited {     color: #03c }
tr.row:hover {  background-color: #79a }
th, td {        border: solid #79a 1px }
td {            padding: 1px 0 1px 1px }
select, input.date, footer { text-align: center }
select, #todo input { border-width: 0; font-size: 116%; line-height: 1.4 }
select {        width: 3.8em; height: 1.7em }
input.todo {    padding-left: 0.5em; width: 16em }
input.date {    width: 5.8em }
#update {       text-align: right }
#update input { padding: 0.7em 2em; line-height: 1.6 }
li {            padding-top: 2em }
li a {          font-size: 131% }
li#add input {  width: 4.9em }
#ft {           height: 2em }
.ui-datepicker td span, .ui-datepicker td a { text-align: center }
</style>
</head>

<body>

<div id="doc" class="yui-t2">

<header id="hd"><h1><a href="?">TODO</a></h1></header>

<div id="bd">

<div id="yui-main"><div class="yui-b">

<article>
<?php
if ($todo->isValidCat()) {
    ?>
    <form method="POST">

    <div id="cat">
    カテゴリ
    <input type="text" name="cat" value="<?php echo h($todo->cat) ?>" />
    <input type="hidden" name="oldcat" value="<?php echo h($todo->cat) ?>" />
    </div>

    <!-- TODOリスト -->
    <table id="todo">
    <thead>
    <tr><th>優先度</th><th>TODO</th><th>開始日</th><th>期限</th><th>状態</th></tr>
    </thead>

    <tbody>
    <?php
    $styleClasses = array('', 'todo', 'date han', 'date han');
    $statuses = array('<!-- -->', '保留', '完了');

    foreach ($todo->list as $i => $row) {
        $task = explode("\t", $row);
        if ($task[4] == $statuses[2]) {    //完了は出力しない
            continue;
        }
        ?>
        <tr class="row">

        <!-- 優先度 -->
        <td>
        <select name="todo[<?php echo $i ?>][]">
        <?php
        for ($j = 1; $j < Todo::PRI_MAX + 1; $j++) {
            echoOption($j, $task[0]);
        }
        ?>
        </select>
        </td>

        <!-- TODO、開始日、期限 -->
        <?php
        for ($j = 1; $j < 4; $j++) {
            ?>
            <td>
            <input type="text" name="todo[<?php echo $i ?>][]"
             value="<?php echo h($task[$j]) ?>" class="<?php echo $styleClasses[$j] ?>" />
            </td>
            <?php
        }
        ?>

        <!-- 状態 -->
        <td>
        <select name="todo[<?php echo $i ?>][]" title="「完了」にするとリストからなくなります">
        <?php
        foreach ($statuses as $status) {
            echoOption($status, $task[4]);
        }
        ?>
        </select>
        </td>

        </tr>
        <?php
    }
    ?>
    </tbody>
    </table>

    <div id="update"><input type="submit" name="update" value="更新" /></div>

    </form>
    <?php
} else if ($todo->cat) {
    ?>
    <span class="error">
    残念ですが、このカテゴリ名（ <?php echo h($todo->cat) ?> ）は使えません
    </span>
    <?php
}
?>
</article>

</div></div>

<!-- カテゴリリスト -->
<nav class="yui-b">
<ul>
<?php
foreach ($todo->cats as $cat => $fileSize) {
    $href = '?cat=' . rawurlencode($cat);
    ?>
    <li>
    <a href="<?php echo $href ?>"><?php echo h($cat) ?></a>
    <?php
    if (!$fileSize) {    //todoが無いカテゴリは削除できる
        ?>
        <a href="<?php echo $href ?>&amp;delete=do" title="カテゴリを削除する">[削除]</a>
        <?php
    }
    ?>
    </li>
    <?php
}
?>

<li id="add">
<form method="POST">
<div>
<input type="text" name="cat" />
<input type="submit" value="追加" title="カテゴリを追加する" />
</div>
</form>
</li>

</ul>
</nav>

</div>

<footer id="ft">
powered by <a href="http://0-oo.net/sbox/php-tool-box/todo">Todo.php</a>
</footer>

</div>

<script src="//cdn.jsdelivr.net/g/jquery,jquery.ui"></script>
<script src="//0-oo.github.io/pryn.js"></script>
<script src="//0-oo.github.io/gcalendar-holidays.js" defer="defer"></script>
<script>
$(function() {
    // カレンダーのオプションはお好みで @see http://api.jqueryui.com/datepicker/
    $(".date").datepicker({
        yearSuffix: "年",
        showMonthAfterYear: true,
        monthNames: (function(m, a){ for (; m < 13; m++) a.push(m + "月"); return a; })(1, []),
        firstDay: 1,
        dayNamesMin: ["日", "月", "火", "水", "木", "金", "土"],
        dateFormat: "yy/m/d",
	constrainInput: false
    });
});
</script>

</body>
</html>
