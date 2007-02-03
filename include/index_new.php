<?php

////////////////////////////////////////////////////////////////////////////////
//                                                                            //
//   Copyright (C) 2006  Phorum Development Team                              //
//   http://www.phorum.org                                                    //
//                                                                            //
//   This program is free software. You can redistribute it and/or modify     //
//   it under the terms of either the current Phorum License (viewable at     //
//   phorum.org) or the Phorum License that was distributed with this file    //
//                                                                            //
//   This program is distributed in the hope that it will be useful,          //
//   but WITHOUT ANY WARRANTY, without even the implied warranty of           //
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     //
//                                                                            //
//   You should have received a copy of the Phorum License                    //
//   along with this program.                                                 //
////////////////////////////////////////////////////////////////////////////////

if(!defined("PHORUM")) return;

if($PHORUM["forum_id"]==0){

    $forums[0] = array(
                    "forum_id" => 0,
                    "folder_flag" => 1,
                    "vroot" => 0
                 );
} else {

    $forums = phorum_db_get_forums( $PHORUM["forum_id"] );
}

if($PHORUM["vroot"]==$PHORUM["forum_id"]){
    $more_forums = phorum_db_get_forums( 0, $PHORUM["forum_id"] );
    foreach($more_forums as $forum_id => $forum){
        if(empty($forums[$forum_id])){
            $forums[$forum_id]=$forum;
        }
    }
    $folders[$PHORUM["forum_id"]]=$PHORUM["forum_id"];
}

$PHORUM["DATA"]["FORUMS"] = array();

$forums_shown=false;

// create the top level folder

foreach( $forums as $key=>$forum ) {
    if($forum["folder_flag"] && $forum["vroot"]==$PHORUM["vroot"]){
        $folders[$key]=$forum["forum_id"];
        $forums[$key]["URL"]["LIST"] = phorum_get_url( PHORUM_INDEX_URL, $forum["forum_id"] );

        $sub_forums = phorum_db_get_forums( 0, $forum["forum_id"] );
        foreach($sub_forums as $sub_forum){
            if(!$sub_forum["folder_flag"]){
                $folder_forums[$sub_forum["parent_id"]][]=$sub_forum;
            }
        }
    }
}

// get newflag count for the vroot
// this is the announcement count
// TODO: cache this too?
list($vroot_new_messages, $vroot_new_threads) = phorum_db_newflag_get_unread_count($PHORUM["vroot"]);

foreach( $folders as $folder_key=>$folder_id ) {

    if(!isset($folder_forums[$folder_id])) continue;

    $shown_sub_forums=array();

    foreach($folder_forums[$folder_id] as $key=>$forum){

        if($PHORUM["hide_forums"] && !phorum_user_access_allowed(PHORUM_USER_ALLOW_READ, $forum["forum_id"])){
            unset($folder_forums[$folder_id][$key]);
            continue;
        }

        $forum["URL"]["LIST"] = phorum_get_url( PHORUM_LIST_URL, $forum["forum_id"] );
        $forum["URL"]["MARK_READ"] = phorum_get_url( PHORUM_INDEX_URL, $forum["forum_id"], "markread", $PHORUM['forum_id'] );
        if(isset($PHORUM['use_rss']) && $PHORUM['use_rss']) {
            $forum["URL"]["FEED"] = phorum_get_url( PHORUM_FEED_URL, $forum["forum_id"], "type=".$PHORUM["default_feed"] );
        }


        if ( $forum["message_count"] > 0 ) {
            $forum["raw_last_post"] = $forum["last_post_time"];
            $forum["last_post"] = phorum_date( $PHORUM["long_date_time"], $forum["last_post_time"] );
        } else {
            $forum["last_post"] = "&nbsp;";
        }

        $forum["message_count"] = number_format($forum["message_count"], 0, $PHORUM["dec_sep"], $PHORUM["thous_sep"]);
        $forum["thread_count"] = number_format($forum["thread_count"], 0, $PHORUM["dec_sep"], $PHORUM["thous_sep"]);

        if($PHORUM["DATA"]["LOGGEDIN"] && $PHORUM["show_new_on_index"]){

            $newflagcounts = null;
            if($PHORUM['cache_newflags']) {
                $newflagkey    = $forum["forum_id"]."-".$PHORUM['user']['user_id'];
                $newflagcounts = phorum_cache_get('newflags_index',$newflagkey,$forum['cache_version']);
            }

            if($newflagcounts == null) {
                $newflagcounts = phorum_db_newflag_get_unread_count($forum["forum_id"]);
                if($PHORUM['cache_newflags']) {
                    phorum_cache_put('newflags_index',$newflagkey,$newflagcounts,86400,$forum['cache_version']);
                }
            }

            list($forum["new_messages"], $forum["new_threads"]) = $newflagcounts;
            $forum["new_messages"] += $vroot_new_messages;
            $forum["new_threads"] += $vroot_new_threads;

            $forum["new_messages"] = number_format($forum["new_messages"], 0, $PHORUM["dec_sep"], $PHORUM["thous_sep"]);
            $forum["new_threads"] = number_format($forum["new_threads"], 0, $PHORUM["dec_sep"], $PHORUM["thous_sep"]);
        }

        $shown_sub_forums[] = $forum;

    }

    if(count($shown_sub_forums)){
        $PHORUM["DATA"]["FORUMS"][]=$forums[$folder_key];
        $PHORUM["DATA"]["FORUMS"]=array_merge($PHORUM["DATA"]["FORUMS"], $shown_sub_forums);
    }

}

// set all our URL's
phorum_build_common_urls();

if(!count($PHORUM["DATA"]["FORUMS"])){
    include phorum_get_template( "header" );
    phorum_hook("after_header");
    $PHORUM["DATA"]["OKMSG"]=$PHORUM["DATA"]["LANG"]["NoForums"];
    include phorum_get_template( "message" );
    phorum_hook("before_footer");
    include phorum_get_template( "footer" );
    return;
}

// should we show the top-link?
if($PHORUM['forum_id'] == 0 || $PHORUM['vroot'] == $PHORUM['forum_id']) {
    unset($PHORUM["DATA"]["URL"]["INDEX"]);
}

$PHORUM["DATA"]["FORUMS"]=phorum_hook("index", $PHORUM["DATA"]["FORUMS"]);

include phorum_get_template( "header" );
phorum_hook("after_header");
include phorum_get_template( "index_new" );
phorum_hook("before_footer");
include phorum_get_template( "footer" );

?>
