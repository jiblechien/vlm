<?php

session_start();
include_once("config.php");
include_once("functions.php");
require_once("externals/xmpp-prebind-php/lib/XmppPrebind.php");

#FIXME: Should throw correct http error
if (!isPlayerLoggedIn()) die("Not logged");
$p = getLoggedPlayerObject();

$xmppPrebind = new XmppPrebind(VLM_XMPP_HOST, VLM_XMPP_HTTP_BIND, 'site', false, false);
$xmppPrebind->connect($p->playername, $p->password);
$xmppPrebind->auth();

$sessionInfo = $xmppPrebind->getSessionInfo(); // array containing sid, rid and jid

header("Content-type: application/json; charset=UTF-8");
echo json_encode($sessionInfo);

?>
