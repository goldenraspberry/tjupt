<?php
require_once("include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require(get_langfile_path("", true));
loggedinorreturn();
parked();
global $CURUSER, $commanage_class, $addcomment_bonus;

$action = htmlspecialchars($_GET ["action"]);
$sub = htmlspecialchars($_GET ["sub"]);
$type = htmlspecialchars($_GET ["type"]);

function check_comment_type($type)
{
    if ($type != "torrent" && $type != "request" && $type != "offer") {
        global $lang_comment;
        stderr($lang_comment ['std_error'], $lang_comment ['std_error']);
    }
}

check_comment_type($type);

if ($action == "add") {

    if ($_SERVER ["REQUEST_METHOD"] == "POST") {
        // Anti Flood Code
        // This code ensures that a member can only send one comment per minute.
        if (get_user_class() < $commanage_class) {
            if (strtotime($CURUSER ['last_comment']) > (TIMENOW - 10)) {
                $secs = 10 - (TIMENOW - strtotime($CURUSER ['last_comment']));
                stderr($lang_comment ['std_error'], $lang_comment ['std_comment_flooding_denied'] . "$secs" . $lang_comment ['std_before_posting_another']);
            }
        }

        $parent_id = 0 + $_POST ["pid"];
        int_check($parent_id, true);

        if ($type == "torrent")
            $res = sql_query("SELECT name, owner FROM torrents WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
        else if ($type == "offer")
            $res = sql_query("SELECT name, userid as owner FROM offers WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
        else if ($type == "request")
            $res = sql_query("SELECT req.name as name, userid as owner FROM req WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);

        $arr = mysql_fetch_array($res);
        if (!$arr)
            stderr($lang_comment ['std_error'], $lang_comment ['std_no_torrent_id']);

        $text = trim($_POST ["body"]);


        if (!$text)
            stderr($lang_comment ['std_error'], $lang_comment ['std_comment_body_empty']);
        if (check_emotion($text))
            stderr($lang_comment ['std_error'], "你不认为评论内容很没劲吗,返回重写吧！");
        if ($type == "torrent") {
            if ((strstr($text, "字幕") || strstr($text, "速度") || strstr($text, "种子")) && !$_POST ["confirm"])
                stderr("注意：", "在评论中求字幕、求补种、催速均是违规行为，你确认要发布？(如果你不是在做这些事，可以无视本提示。)<form id=compose name=comment method=post action=comment.php?action=add&type=torrent ><input type=hidden name=pid value=" . $parent_id . " /><input type=hidden name=confirm value=yes /><br /><input type=hidden name=body value=\"" . $text . "\"><br/><input type=submit value=\"确认\">&nbsp;<input type=button onclick=\"location.href='javascript:history.go(-1)'\" value=取消></form>", 0);
            if ((strstr($text, "烂片") || strstr($text, "垃圾")) && !$_POST ["confirm"])
                stderr("注意：", "在评论中发表否定性评价会影响发布者积极性，你确认要发布？(如果你不是在做这些事，可以无视本提示。)<form id=compose name=comment method=post action=comment.php?action=add&type=torrent ><input type=hidden name=pid value=" . $parent_id . " /><input type=hidden name=confirm value=yes /><br /><input type=hidden name=body value=\"" . $text . "\"><br/><input type=submit value=\"确认\">&nbsp;<input type=button onclick=\"location.href='javascript:history.go(-1)'\" value=取消></form>", 0);
            if ((stristr($text, "FREE") || strstr($text, "免费")) && !$_POST ["confirm"])
                stderr("注意：", "在评论中求free是违规行为，你确认要发布？(如果你不是在做这些事，可以无视本提示。)<form id=compose name=comment method=post action=comment.php?action=add&type=torrent ><input type=hidden name=pid value=" . $parent_id . " /><input type=hidden name=confirm value=yes /><br /><input type=hidden name=body value=\"" . $text . "\"><br/><input type=submit value=\"确认\">&nbsp;<input type=button onclick=\"location.href='javascript:history.go(-1)'\" value=取消></form>", 0);

            sql_query("INSERT INTO comments (user, torrent, added, text, ori_text) VALUES (" . $CURUSER ["id"] . ",$parent_id, '" . date("Y-m-d H:i:s") . "', " . sqlesc($text) . "," . sqlesc($text) . ")");
            $Cache->delete_value('torrent_' . $parent_id . '_last_comment_content');
        } elseif ($type == "offer") {
            sql_query("INSERT INTO comments (user, offer, added, text, ori_text) VALUES (" . $CURUSER ["id"] . ",$parent_id, '" . date("Y-m-d H:i:s") . "', " . sqlesc($text) . "," . sqlesc($text) . ")");
            $Cache->delete_value('offer_' . $parent_id . '_last_comment_content');
        } elseif ($type == "request")
            sql_query("INSERT INTO comments (user, request, added, text, ori_text) VALUES (" . $CURUSER ["id"] . ",$parent_id, '" . date("Y-m-d H:i:s") . "', " . sqlesc($text) . "," . sqlesc($text) . ")");

        $newid = mysql_insert_id();

        if ($type == "torrent")
            sql_query("UPDATE torrents SET comments = comments + 1 WHERE id = $parent_id");
        else if ($type == "offer")
            sql_query("UPDATE offers SET comments = comments + 1 WHERE id = $parent_id");
        else if ($type == "request")
            sql_query("UPDATE req SET comments = comments + 1 WHERE id = $parent_id");

        $ras = sql_query("SELECT commentpm FROM users WHERE id = $arr[owner]") or sqlerr(__FILE__, __LINE__);
        $arg = mysql_fetch_array($ras);

        if ($arg ["commentpm"] == 'yes' && $CURUSER ['id'] != $arr ["owner"]) {
            $added = sqlesc(date("Y-m-d H:i:s"));
            $subject = sqlesc($lang_comment_target [get_user_lang($arr ["owner"])] ['msg_new_comment']);
            if ($type == "torrent")
                $notifs = sqlesc($lang_comment_target [get_user_lang($arr ["owner"])] ['msg_torrent_receive_comment'] . " [url=details.php?id=$parent_id] " . $arr ['name'] . "[/url].");
            if ($type == "offer")
                $notifs = sqlesc($lang_comment_target [get_user_lang($arr ["owner"])] ['msg_offer_receive_comment'] . " [url=offers.php?id=$parent_id&off_details=1] " . $arr ['name'] . "[/url].");
            if ($type == "request")
                $notifs = sqlesc($lang_comment_target [get_user_lang($arr ["owner"])] ['msg_request_receive_comment'] . " [url=viewrequests.php?action=view&id=$parent_id] " . $arr ['name'] . "[/url].");

            sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $arr ['owner'] . ", $subject, $notifs, $added)") or sqlerr(__FILE__, __LINE__);
            $Cache->delete_value('user_' . $arr ['owner'] . '_unread_message_count');
            $Cache->delete_value('user_' . $arr ['owner'] . '_inbox_count');
        }

        KPS("+", $addcomment_bonus, $CURUSER ["id"]);

        // Update Last comment sent...
        sql_query("UPDATE users SET last_comment = NOW() WHERE id = " . sqlesc($CURUSER ['id'])) or sqlerr(__FILE__, __LINE__);

        // PM被@的用户
        if ($type == "torrent") {
            $msg = '请点击 [b][url=details.php?id=' . $parent_id . '&hit=1#cid' . $newid . ']' . $arr ["name"] . '[/url][/b] 查看。';
        } else if ($type == "offer") {
            $msg = '请点击 [b][url=offers.php?id=' . $parent_id . '&off_details=1#cid' . $newid . ']' . $arr ["name"] . '[/url][/b] 查看。';
        } else if ($type == "request") {
            $msg = '请点击 [b][url=viewrequests.php?action=view&id=' . $parent_id . '&req_details=1#cid' . $newid . ']' . $arr ["name"] . '[/url][/b] 查看。';
        }
        pm_at_users($text, $msg, $type);

        if ($type == "torrent")
            header("Refresh: 0; url=details.php?id=$parent_id#$newid");
        else if ($type == "offer")
            header("Refresh: 0; url=offers.php?id=$parent_id&off_details=1#$newid");
        else if ($type == "request")
            header("Refresh: 0; url=viewrequests.php?action=view&id=$parent_id&req_details=1#cid$newid");
        die ();
    }

    $parent_id = 0 + $_GET ["pid"];
    int_check($parent_id, true);

    if ($sub == "quote") {
        $commentid = 0 + $_GET ["cid"];
        int_check($commentid, true);

        $res2 = sql_query("SELECT comments.text, users.username FROM comments JOIN users ON comments.user = users.id WHERE comments.id=$commentid") or sqlerr(__FILE__, __LINE__);

        if (mysql_num_rows($res2) != 1)
            stderr($lang_forums ['std_error'], $lang_forums ['std_no_comment_id']);

        $arr2 = mysql_fetch_assoc($res2);
    }

    if ($type == "torrent") {
        $res = sql_query("SELECT name, owner FROM torrents WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
        $url = "details.php?id=$parent_id";
    } else if ($type == "offer") {
        $res = sql_query("SELECT name, userid as owner FROM offers WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
        $url = "offers.php?id=$parent_id&off_details=1";
    } else if ($type == "request") {
        $res = sql_query("SELECT req.name as name, userid as owner FROM req WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
        $url = "viewrequests.php?action=view&id=$parent_id&req_details=1";
    }
    $arr = mysql_fetch_array($res);
    if (!$arr)
        stderr($lang_comment ['std_error'], $lang_comment ['std_no_torrent_id']);

    stdhead($lang_comment ['head_add_comment_to'] . $arr ["name"]);
    begin_main_frame();
    $title = $lang_comment ['text_add_comment_to'] . "<a href=$url>" . htmlspecialchars($arr ["name"]) . "</a>";
    print ("<form id=compose method=post name=\"compose\" action=\"comment.php?action=add&type=$type\">\n");
    print ("<input type=\"hidden\" name=\"pid\" value=\"$parent_id\"/>\n");
    begin_compose($title, ($sub == "quote" ? "quote" : "reply"), ($sub == "quote" ? htmlspecialchars("[quote=" . htmlspecialchars($arr2 ["username"]) . "]" . $arr2 ["text"] . "[/quote] @" . htmlspecialchars($arr2 ["username"]) . " ") : ""), false);
    end_compose();
    print ("</form>");
    end_main_frame();
    stdfoot();
    die ();
} elseif ($action == "edit") {
    $commentid = 0 + $_GET ["cid"];
    int_check($commentid, true);

    if ($type == "torrent")
        $res = sql_query("SELECT c.*, t.name, t.id AS parent_id FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id=$commentid") or sqlerr(__FILE__, __LINE__);
    else if ($type == "offer")
        $res = sql_query("SELECT c.*, o.name, o.id AS parent_id FROM comments AS c JOIN offers AS o ON c.offer = o.id WHERE c.id=$commentid") or sqlerr(__FILE__, __LINE__);
    else if ($type == "request")
        $res = sql_query("SELECT c.*, r.name as name, r.id AS parent_id FROM comments AS c JOIN req AS r ON c.request = r.id WHERE c.id=$commentid") or sqlerr(__FILE__, __LINE__);

    $arr = mysql_fetch_array($res);
    if (!$arr)
        stderr($lang_comment ['std_error'], $lang_comment ['std_invalid_id']);

    if ($arr ["user"] != $CURUSER ["id"] && get_user_class() < $commanage_class)
        stderr($lang_comment ['std_error'], $lang_comment ['std_permission_denied']);

    if ($_SERVER ["REQUEST_METHOD"] == "POST") {
        $text = $_POST ["body"];
        $returnto = htmlspecialchars($_POST ["returnto"]) ? $_POST ["returnto"] : htmlspecialchars($_SERVER ["HTTP_REFERER"]);

        if ($text == "")
            stderr($lang_comment ['std_error'], $lang_comment ['std_comment_body_empty']);
        $text = sqlesc($text);
        $editdate = sqlesc(date("Y-m-d H:i:s"));

        sql_query("UPDATE comments SET text=$text, editdate=$editdate, editedby=$CURUSER[id] WHERE id=" . sqlesc($commentid)) or sqlerr(__FILE__, __LINE__);
        if ($type == "torrent")
            $Cache->delete_value('torrent_' . $arr ['parent_id'] . '_last_comment_content');
        elseif ($type == "offer")
            $Cache->delete_value('offer_' . $arr ['parent_id'] . '_last_comment_content');
        header("Location: $returnto");

        die ();
    }
    $parent_id = $arr ["parent_id"];
    if ($type == "torrent")
        $url = "details.php?id=$parent_id";
    else if ($type == "offer")
        $url = "offers.php?id=$parent_id&off_details=1";
    else if ($type == "request")
        $url = "viewrequests.php?action=view&id=$parent_id&req_details=1";
    stdhead($lang_comment ['head_edit_comment_to'] . "\"" . $arr ["name"] . "\"");
    begin_main_frame();
    $title = $lang_comment ['head_edit_comment_to'] . "<a href=$url>" . htmlspecialchars($arr ["name"]) . "</a>";
    print ("<form id=compose method=post name=\"compose\" action=\"comment.php?action=edit&cid=$commentid&type=$type\">\n");
    print ("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($_SERVER ["HTTP_REFERER"]) . "\" />\n");
    begin_compose($title, "edit", htmlspecialchars($arr ["text"]), false);
    end_compose();
    print ("</form>");
    end_main_frame();
    stdfoot();
    die ();
} elseif ($action == "delete") {
    if (get_user_class() < $commanage_class)
        stderr($lang_comment ['std_error'], $lang_comment ['std_permission_denied']);

    $commentid = 0 + $_GET ["cid"];
    $sure = $_GET ["sure"];
    int_check($commentid, true);

    if (!$sure) {
        $referer = $_SERVER ["HTTP_REFERER"];
        stderr($lang_comment ['std_delete_comment'], $lang_comment ['std_delete_comment_note'] . "<a href=comment.php?action=delete&cid=$commentid&sure=1&type=$type" . ($referer ? "&returnto=" . rawurlencode($referer) : "") . $lang_comment ['std_here_if_sure'], false);
    } else
        int_check($sure, true);

    if ($type == "torrent")
        $res = sql_query("SELECT torrent as pid,user FROM comments WHERE id=$commentid") or sqlerr(__FILE__, __LINE__);
    else if ($type == "offer")
        $res = sql_query("SELECT offer as pid,user FROM comments WHERE id=$commentid") or sqlerr(__FILE__, __LINE__);
    else if ($type == "request")
        $res = sql_query("SELECT request as pid,user FROM comments WHERE id=$commentid") or sqlerr(__FILE__, __LINE__);

    $arr = mysql_fetch_array($res);
    if ($arr) {
        $parent_id = $arr ["pid"];
        $userpostid = $arr ["user"];
    } else
        stderr($lang_comment ['std_error'], $lang_comment ['std_invalid_id']);

    sql_query("DELETE FROM comments WHERE id=$commentid") or sqlerr(__FILE__, __LINE__);
    if ($type == "torrent")
        $Cache->delete_value('torrent_' . $arr ['pid'] . '_last_comment_content');
    elseif ($type == "offer")
        $Cache->delete_value('offer_' . $arr ['pid'] . '_last_comment_content');
    if ($parent_id && mysql_affected_rows() > 0) {
        if ($type == "torrent")
            sql_query("UPDATE torrents SET comments = comments - 1 WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
        else if ($type == "offer")
            sql_query("UPDATE offers SET comments = comments - 1 WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
        else if ($type == "request")
            sql_query("UPDATE req SET comments = comments - 1 WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
    }

    KPS("-", $addcomment_bonus, $userpostid);

    $returnto = $_GET ["returnto"] ? $_GET ["returnto"] : htmlspecialchars($_SERVER ["HTTP_REFERER"]);

    header("Location: $returnto");

    die ();
} elseif ($action == "vieworiginal") {
    if (get_user_class() < $commanage_class)
        stderr($lang_comment ['std_error'], $lang_comment ['std_permission_denied']);

    $commentid = 0 + $_GET ["cid"];
    int_check($commentid, true);

    if ($type == "torrent")
        $res = sql_query("SELECT c.*, t.name FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id=$commentid") or sqlerr(__FILE__, __LINE__);
    else if ($type == "offer")
        $res = sql_query("SELECT c.*, o.name FROM comments AS c JOIN offers AS o ON c.offer = o.id WHERE c.id=$commentid") or sqlerr(__FILE__, __LINE__);
    else if ($type == "request")
        $res = sql_query("SELECT c.*, r.name as name FROM comments AS c JOIN req AS r ON c.request = r.id WHERE c.id=$commentid") or sqlerr(__FILE__, __LINE__);

    $arr = mysql_fetch_array($res);
    if (!$arr)
        stderr($lang_comment ['std_error'], $lang_comment ['std_invalid_id']);

    stdhead($lang_comment ['head_original_comment']);
    print ("<h1>" . $lang_comment ['text_original_content_of_comment'] . "#$commentid</h1>");
    print ("<table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">");
    print ("<tr><td class=\"text\">\n");
    echo format_comment($arr ["ori_text"]);
    print ("</td></tr></table>\n");

    $returnto = htmlspecialchars($_SERVER ["HTTP_REFERER"]);

    if ($returnto)
        print ("<p><font size=\"small\">(<a href=\"" . $returnto . "\">" . $lang_comment ['text_back'] . "</a>)</font></p>\n");

    stdfoot();

    die ();
} elseif ($action == "sticky"){

    if ($type != "torrent"){
        stderr($lang_comment ['std_error'], $lang_comment ['std_permission_denied']);
    }

    $commentid = 0 + $_GET ["cid"];
    int_check($commentid, true);

    $res = sql_query("SELECT torrent as pid,is_sticky FROM comments WHERE id=$commentid") or sqlerr(__FILE__, __LINE__);
    $arr = mysql_fetch_array($res);
    if ($arr) {
        $parent_id = $arr ["pid"];
        $isTop = $arr ["is_sticky"];
    } else
        stderr($lang_comment ['std_error'], $lang_comment ['std_invalid_id']);

    $isOwner = false;
    $res = sql_query("SELECT name, owner FROM torrents WHERE id = $parent_id") or sqlerr(__FILE__, __LINE__);
    $arr = mysql_fetch_array($res);
    if (get_user_class() < $commanage_class){
        if ($arr['owner'] != $CURUSER ["id"]){
            stderr($lang_comment ['std_error'], $lang_comment ['std_permission_denied']);
        }
        $isOwner = true;
    }

    $sticky = 0 + $_GET['sticky'];
    int_check($sticky, true);
    if ($isTop != $sticky){
        $res = mysql_query("UPDATE comments SET is_sticky=" . $sticky . " WHERE id = " . $commentid) or sqlerr(__FILE__, __LINE__);

        $actSticky = $sticky == 0 ? "取消置顶" : "置顶";

        write_log(($isOwner ? "发布者 " : "管理员 ") ."$CURUSER[username] 编辑了种子 {$arr['name']} 评论 $commentid $actSticky");
    }

    $returnto = $_GET ["returnto"] ? $_GET ["returnto"] : htmlspecialchars($_SERVER ["HTTP_REFERER"]);

    header("Location: $returnto");

    die ();
}else
    stderr($lang_comment ['std_error'], $lang_comment ['std_unknown_action']);

die ();
