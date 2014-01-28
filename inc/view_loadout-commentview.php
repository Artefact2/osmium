<?php
/* Osmium
 * Copyright (C) 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Osmium\ViewLoadout;

echo "<h2>Comments</h2>\n";

$cancomment = !\Osmium\Reputation\is_fit_public($fit) || \Osmium\Reputation\has_privilege(
	\Osmium\Reputation\PRIVILEGE_COMMENT_LOADOUT
) || (isset($author['accountid']) && isset($a['accountid']) && $a['accountid'] == $author['accountid']);
$canreply = !\Osmium\Reputation\is_fit_public($fit) || \Osmium\Reputation\has_privilege(
	\Osmium\Reputation\PRIVILEGE_REPLY_TO_COMMENTS
);

if($commentcount === 0) {
	echo "<p class='placeholder'>This loadout has no comments.</p>\n";
	goto addcomment; /* Yeah, this isn't very cool, but it avoid
	                  * putting the following code in a else block and
	                  * wasting one indentation level */
}

$offset = \Osmium\Chrome\paginate(
	'pagec',
	$commentsperpage,
	$commentcount,
	$result,
	$metaresult,
	null,
	'',
	'#comments'
);

$commentidsq = \Osmium\Db\query_params(
	'SELECT commentid FROM osmium.loadoutcomments
	WHERE loadoutid = $1 AND revision <= $2
	ORDER BY revision DESC, creationdate DESC
	LIMIT $3 OFFSET $4',
	array(
		$loadoutid,
		$revision,
	    $commentsperpage,
		$offset
	)
);

$commentids = array(-1);
while($r = \Osmium\Db\fetch_row($commentidsq)) {
	$commentids[] = $r[0];
}

/* Here we go… The big query of death™ */
$cq = \Osmium\Db\query_params(
	'SELECT
	
	lc.commentid, lc.accountid, lc.creationdate, lc.revision AS loadoutrevision,

	lcudv.votes, lcudv.upvotes, lcudv.downvotes, v.type AS votetype, lcrev.revision AS commentrevision,

	lcrev.updatedbyaccountid, lcrev.updatedate, lcrev.commentformattedbody,

	lcrep.commentreplyid, lcrep.creationdate AS repcreationdate,
	lcrep.replyformattedbody, lcrep.updatedate AS repupdatedate,

	cacc.accountid, cacc.nickname, cacc.apiverified, cacc.characterid,
	cacc.charactername, cacc.ismoderator, cacc.reputation,

	racc.accountid AS raccountid, racc.nickname AS rnickname, racc.apiverified AS rapiverified,
	racc.characterid AS rcharacterid, racc.charactername AS rcharactername, racc.ismoderator AS rismoderator,


	uacc.accountid AS uaccountid, uacc.nickname AS unickname, uacc.apiverified AS uapiverified,
	uacc.characterid AS ucharacterid, uacc.charactername AS ucharactername,
	uacc.ismoderator AS uismoderator, uacc.reputation AS ureputation

	FROM osmium.loadoutcomments AS lc
	JOIN osmium.loadoutcommentupdownvotes AS lcudv ON lcudv.commentid = lc.commentid
	LEFT JOIN osmium.votes AS v ON (v.targettype = $1 AND v.type IN ($2, $3)
	AND v.fromaccountid = $4 AND v.targetid1 = lc.commentid
	AND v.targetid2 = lc.loadoutid AND v.targetid3 IS NULL)
	JOIN osmium.accounts AS cacc ON cacc.accountid = lc.accountid
	JOIN osmium.loadoutcommentslatestrevision AS lclr ON lc.commentid = lclr.commentid
	JOIN osmium.loadoutcommentrevisions AS lcrev ON lcrev.commentid = lc.commentid
	AND lcrev.revision = lclr.latestrevision
	JOIN osmium.accounts AS uacc ON uacc.accountid = lcrev.updatedbyaccountid
	LEFT JOIN osmium.loadoutcommentreplies AS lcrep ON lcrep.commentid = lc.commentid
	LEFT JOIN osmium.accounts AS racc ON racc.accountid = lcrep.accountid
	WHERE lc.commentid IN ('.implode(',', $commentids).')
	ORDER BY lc.revision DESC, lcrep.creationdate ASC',
	array(
		\Osmium\Reputation\VOTE_TARGET_TYPE_COMMENT,
		\Osmium\Reputation\VOTE_TYPE_UP,
		\Osmium\Reputation\VOTE_TYPE_DOWN,
		$loggedin ? $a['accountid'] : 0,
	)
);

function format_comment($row) {
	global $isflaggable, $ismoderator, $modprefix, $loggedin, $a, $fit;

	echo "<div class='comment' id='c".$row['commentid']."' data-commentid='".$row['commentid']."'>\n";

	echo "<div class='votes' data-targettype='comment'>\n";
	echo "<a title='This comment is useful' class='upvote"
		.($row['votetype'] == \Osmium\Reputation\VOTE_TYPE_UP ? ' voted' : '')
		."'><img src='".RELATIVE."/static-".\Osmium\STATICVER."/icons/vote.svg' alt='upvote' /></a>\n";
	echo "<strong title='".$row['upvotes']." upvote(s), "
		.$row['downvotes']." downvote(s)'>".$row['votes']."</strong>\n";
	echo "<a title='This comment is off-topic, not constructive or not useful' class='downvote"
		.($row['votetype'] == \Osmium\Reputation\VOTE_TYPE_DOWN ? ' voted' : '')
		."'><img src='".RELATIVE."/static-".\Osmium\STATICVER."/icons/vote.svg' alt='downvote' /></a>\n";
	echo "</div>\n";

	echo "<div class='body'>\n".$row['commentformattedbody']."</div>\n";

	echo "<header>\n<div class='author'>\n";
	if($row['apiverified'] === 't' && $row['characterid'] > 0) {
		echo "<img class='portrait' src='//image.eveonline.com/Character/"
			.$row['characterid']."_256.jpg' alt='' />";
	}
	echo "<small>commented by</small><br />\n";
	echo \Osmium\Chrome\format_character_name($row, RELATIVE)."<br />\n";
	echo \Osmium\Chrome\format_reputation($row['reputation']).' – '
		.\Osmium\Chrome\format_relative_date($row['creationdate'])."\n";
	echo "</div>\n<div class='meta'>\n";
	echo "<a href='?jtc=".$row['commentid']."' title='permanent link'>#</a>";
	if($isflaggable) {
		echo " — <a class='dangerous' href='".RELATIVE."/flagcomment/"
			.$row['commentid']."' title='This comment requires moderator attention'>⚑</a>";
	}
	if($ismoderator || ($loggedin && $row['accountid'] == $a['accountid'])) {
		$tmp = ($loggedin && $row['accountid'] == $a['accountid']) ? '' : $modprefix;

		echo " — <a href='".RELATIVE."/editcomment/".$row['commentid']."'>{$tmp}edit</a>";
		echo " — <a href='".RELATIVE."/deletecomment/".$row['commentid']."?tok=".\Osmium\State\get_token()."' class='dangerous confirm'>{$tmp}delete</a>";
	}
	if($row['loadoutrevision'] < $fit['metadata']['revision']) {
		echo "<br />\n<span class='outdated'>(this comment applies to a previous revision of this loadout:";
		echo " <a href='".RELATIVE.'/'.\Osmium\Fit\get_fit_uri(
			$fit['metadata']['loadoutid'],
			$fit['metadata']['visibility'],
			$fit['metadata']['privatetoken'],
			$row['loadoutrevision']
		)."'>revision #".$row['loadoutrevision']."</a>)</span>\n";
	}
	echo "</div>\n</header>\n<ul id='creplies".$row['commentid']."' class='replies'>\n";
}

function format_comment_end($row) {
	global $loggedin, $commentsallowed, $canreply, $a;

	$commentid = $row['commentid'];
	$commentauthorid = $row['accountid'];

	if($loggedin && $commentsallowed && ($canreply || $commentauthorid == $a['accountid'])) {
		echo "<li class='new'>\n";
		echo "<form method='post' action='#creplies".$commentid."' accept-charset='utf-8'>\n";
		echo "<textarea name='replybody' placeholder='Type your reply… (Markdown and some HTML allowed, basic formatting only)'></textarea>\n";
		echo "<input type='hidden' name='commentid' value='".$commentid."' />\n";
		echo "<input type='submit' value='Submit reply' />\n";
		echo "<a class='cancel'>cancel</a>\n</form>\n</li>\n";
	}

	echo "</ul>\n";
	if($loggedin && $commentsallowed && ($canreply || $commentauthorid == $a['accountid'])) {
		echo "<a class='add_comment'>reply to this comment</a>\n";
	}
	echo "</div>\n";
}

function format_comment_reply($row) {
	global $ismoderator, $loggedin, $isflaggable, $a, $modprefix;

	$c = array(
		'accountid' => $row['raccountid'],
		'nickname' => $row['rnickname'],
		'apiverified' => $row['rapiverified'],
		'characterid' => $row['rcharacterid'],
		'charactername' => $row['rcharactername'],
		'ismoderator' => $row['rismoderator']
	);

	echo "<li id='r".$row['commentreplyid']."'>\n<div class='body'>\n"
		.$row['replyformattedbody']."</div>\n— "
		.\Osmium\Chrome\format_character_name($c, RELATIVE)."\n";

	if($row['repupdatedate'] !== null) {
		echo "<span class='updated' title='This reply was edited (".
			strip_tags(\Osmium\Chrome\format_relative_date($row['repupdatedate']))
			.").'>✎</span>\n";
	}

	echo " — ".\Osmium\Chrome\format_relative_date($row['repcreationdate'])."\n";
	echo "<span class='meta'>\n— <a href='?jtr=".$row['commentreplyid']."' title='permament link'>#</a>";
	if($isflaggable) {
		echo " — <a class='dangerous' href='".RELATIVE."/flagcommentreply/"
			.$row['commentreplyid']."' title='This comment reply requires moderator attention'>⚑</a>\n";
	}
	if($ismoderator || ($loggedin && $row['raccountid'] == $a['accountid'])) {
		$tmp = ($loggedin && $row['raccountid'] == $a['accountid']) ? '' : $modprefix;

		echo " — <a href='".RELATIVE."/editcommentreply/".$row['commentreplyid']."'>{$tmp}edit</a>\n";
		echo " — <a href='".RELATIVE."/deletecommentreply/".$row['commentreplyid']."?tok=".\Osmium\State\get_token()."' class='dangerous confirm'>{$tmp}delete</a>\n";
	}
	echo "</span>\n</li>\n";
}

$prevcid = null;
$prevrow = null;
while($row = \Osmium\Db\fetch_assoc($cq)) {
	if($row['commentid'] !== $prevcid) {
		if($prevcid !== null) {
			format_comment_end($prevrow);
		}
		format_comment($row);
		$prevcid = $row['commentid'];
		$prevrow = $row;
	}

	if($row['commentreplyid'] !== null) {
		format_comment_reply($row);
	}
}
if($prevcid !== null) {
	format_comment_end($prevrow);
}

addcomment:
echo "<h2>Add a comment</h2>\n";

if($commentsallowed && $loggedin && $cancomment) {
	\Osmium\Forms\print_form_begin(\Osmium\Chrome\escape($_SERVER['REQUEST_URI']).'#comments');
	\Osmium\Forms\print_textarea(
		'Comment body<br /><small>(Markdown and some HTML allowed)</small>',
		'commentbody',
		'commentbody');
	\Osmium\Forms\print_submit('Submit comment');
	\Osmium\Forms\print_form_end();
} else if($loggedin && $commentsallowed) {
	echo "<p class='placeholder'>You don't have the necessary privilege to comment this loadout.</p>\n";
} else if(!$loggedin && $commentsallowed) {
	echo "<p class='placeholder'>You have to log in to comment on this loadout.</p>\n";
} else if(!$commentsallowed) {
	echo "<p class='placeholder'>This loadout cannot be commented on.</p>\n";
}
