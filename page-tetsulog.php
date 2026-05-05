<?php
/**
 * Template Name: 鉄ログ
 * Description: Minecraft鉄道ギャラリー（YouTube風）
 * PART 1/3 — PHPヘッダー + CSS
 * ※ このファイル単体では動作しません。Part2・Part3と結合してください。
 */

$filter_category = isset($_GET['cat'])  ? sanitize_text_field($_GET['cat'])  : '';
$filter_sort     = isset($_GET['sort']) ? sanitize_key($_GET['sort'])         : 'new';
$filter_type     = isset($_GET['type']) ? sanitize_key($_GET['type'])         : '';

// 編集モード：author.phpから ?edit_tl=ID で遷移してきた場合
$edit_tl_id   = isset($_GET['edit_tl']) ? (int) $_GET['edit_tl'] : 0;
$edit_tl_data = null;
if ($edit_tl_id && is_user_logged_in()) {
    $edit_post = get_post($edit_tl_id);
    if ($edit_post && $edit_post->post_type === 'tetsulog' &&
        ($edit_post->post_author == get_current_user_id() || current_user_can('manage_options'))) {
        $edit_media_key = get_post_meta($edit_tl_id, '_tetsulog_media_key', true);
        $edit_media_url = '';
        if ($edit_media_key && function_exists('generate_garage_signed_url')) {
            $edit_media_url = generate_garage_signed_url($edit_media_key, 3600);
        }
        $edit_tl_data = array(
            'id'          => $edit_tl_id,
            'vehicle'     => get_post_meta($edit_tl_id, '_vehicle_name', true),
            'series'      => get_post_meta($edit_tl_id, '_series_name', true),
            'category'    => get_post_meta($edit_tl_id, '_tetsulog_category', true),
            'addon_id'    => (int) get_post_meta($edit_tl_id, '_linked_addon_id', true),
            'tags'        => get_post_meta($edit_tl_id, '_tetsulog_tags', true),
            'desc'        => get_post_meta($edit_tl_id, '_tetsulog_description', true),
            'type'        => get_post_meta($edit_tl_id, '_tetsulog_type', true) ?: 'image',
            'media_url'   => $edit_media_url,
        );
    }
}

$meta_query = array();
if ($filter_category) {
    $meta_query[] = array('key'=>'_tetsulog_category','value'=>$filter_category,'compare'=>'=');
}
if ($filter_type) {
    $meta_query[] = array('key'=>'_tetsulog_type','value'=>$filter_type,'compare'=>'=');
    if (count($meta_query) > 1) $meta_query['relation'] = 'AND';
}

// 全件取得（セクション分けのため）
$query_args = array(
    'post_type'      => 'tetsulog',
    'post_status'    => 'publish',
    'posts_per_page' => 120,
    'offset'         => 0,
);
if (!empty($meta_query)) $query_args['meta_query'] = $meta_query;

switch ($filter_sort) {
    case 'likes':
        $query_args['meta_key'] = '_tetsulog_likes';
        $query_args['orderby']  = 'meta_value_num';
        $query_args['order']    = 'DESC';
        break;
    default:
        $query_args['orderby'] = 'date';
        $query_args['order']   = 'DESC';
}

$tq          = new WP_Query($query_args);
$total_posts = $tq->found_posts;
$uid         = get_current_user_id();
$logged      = is_user_logged_in();
$tl_nonce    = wp_create_nonce('tetsulog_nonce');
$cats        = array('新幹線','都市鉄道','特急','ローカル線','路面電車','その他');

// 投稿を3セクションに振り分け
$sec_portrait = array(); // 縦動画
$sec_image    = array(); // 画像
$sec_landscape= array(); // 横動画

if ($tq->have_posts()) {
    while ($tq->have_posts()) {
        $tq->the_post();
        $pid   = get_the_ID();
        $mtype = get_post_meta($pid, '_tetsulog_type', true) ?: 'image';
        $orient= get_post_meta($pid, '_tetsulog_orientation', true) ?: 'landscape';

        if ($mtype === 'video' && $orient === 'portrait') {
            $sec_portrait[]  = $pid;
        } elseif ($mtype === 'image') {
            $sec_image[]     = $pid;
        } else {
            $sec_landscape[] = $pid;
        }
    }
    wp_reset_postdata();
}

// カードデータを取得するヘルパー
function tl_get_card_data($pid, $logged, $uid) {
    $media_key  = get_post_meta($pid, '_tetsulog_media_key', true);
    $mtype      = get_post_meta($pid, '_tetsulog_type', true) ?: 'image';
    $orient     = get_post_meta($pid, '_tetsulog_orientation', true) ?: 'landscape';
    $vehicle    = get_post_meta($pid, '_vehicle_name', true) ?: get_the_title($pid);
    $series     = get_post_meta($pid, '_series_name', true);
    $category   = get_post_meta($pid, '_tetsulog_category', true);
    $addon_id   = (int) get_post_meta($pid, '_linked_addon_id', true);
    $tl_tags    = get_post_meta($pid, '_tetsulog_tags', true);
    $tl_desc    = get_post_meta($pid, '_tetsulog_description', true);
    $likes      = (int) get_post_meta($pid, '_tetsulog_likes', true);
    $views      = (int) get_post_meta($pid, '_tetsulog_views', true);
    $liked_arr  = get_post_meta($pid, '_tetsulog_liked_users', true);
    if (!is_array($liked_arr)) $liked_arr = [];
    // 保存形式が文字列("123")・整数(123)どちらでも一致するよう両方チェック
    $is_liked = $logged && (
        in_array((string)$uid, $liked_arr, true) ||
        in_array($uid, $liked_arr, true)
    );
    $post_obj   = get_post($pid);
    $is_owner   = $logged && ($post_obj && $post_obj->post_author == $uid);
    $del_nonce  = wp_create_nonce('delete_tetsulog_'.$pid);
    $media_url  = '';
    if ($media_key && function_exists('generate_garage_signed_url')) {
        $media_url = generate_garage_signed_url($media_key, 3600);
    }
    $addon_title = '';
    $addon_url   = '';
    $addon_thumb = '';
    if ($addon_id && get_post($addon_id)) {
        $addon_title = get_the_title($addon_id);
        $addon_url   = get_permalink($addon_id);
        $addon_thumb = get_the_post_thumbnail_url($addon_id, 'thumbnail') ?: '';
    }
    $author_id     = (int) get_post_field('post_author', $pid);
    $author_obj    = get_userdata($author_id);
    $author_name   = $author_obj ? $author_obj->display_name : '';
    $author_url    = get_author_posts_url($author_id);
    // カスタム→Discord→Gravatar の優先順
    $a_garage = get_user_meta($author_id, 'profile_avatar_garage_key', true);
    $a_dh = get_user_meta($author_id, 'discord_avatar', true);
    $a_du = get_user_meta($author_id, 'discord_user_id', true);
    if ($a_garage && function_exists('generate_garage_signed_url')) {
        $author_avatar = generate_garage_signed_url($a_garage, 86400);
    } elseif (!empty($a_dh) && !empty($a_du)) {
        $author_avatar = "https://cdn.discordapp.com/avatars/{$a_du}/{$a_dh}.png?size=256";
    } else {
        $author_avatar = get_avatar_url($author_id, ['size'=>80]);
    }
    $time_ago      = human_time_diff(get_post_time('U', false, $pid), current_time('timestamp')) . '前';

    return compact(
        'pid','media_key','mtype','orient','vehicle','series','category',
        'addon_id','tl_tags','tl_desc','likes','views','is_liked','is_owner','del_nonce',
        'media_url','addon_title','addon_url','addon_thumb',
        'author_id','author_name','author_url','author_avatar','time_ago'
    );
}

get_header();
?>
<style>
/* ── ナビメニュー・フッターを非表示（スマホのハンバーガーは除く） ── */
.site-footer,#footer,footer.site-footer,footer { display:none !important; }

/* ── ログインポップアップを非表示 ── */
.login-promotion-banner,
#login-modal,
#login-popup,
#login-overlay,
.login-modal,
.login-popup,
.login-overlay,
.login-required-modal,
.login-required-popup { display:none !important; }

/* PC: navを非表示 */
@media(min-width:769px){
    nav.site-navigation,
    .primary-menu-container,
    #primary-menu,
    .main-navigation,
    nav[role="navigation"],
    .site-header nav,
    header nav { display:none !important; }
}

/* スマホ: 通常状態は非表示・ハンバーガー開放後は表示 */
@media(max-width:768px){
    nav.site-navigation,
    .primary-menu-container,
    #primary-menu,
    .main-navigation,
    nav[role="navigation"],
    .site-header nav,
    header nav { display:none; }

    /* ハンバーガーが開いたときに付与されるクラスに対応 */
    .nav-open nav.site-navigation,
    .nav-open .main-navigation,
    .nav-open .primary-menu-container,
    .menu-open nav.site-navigation,
    .menu-open .main-navigation,
    .is-open nav.site-navigation,
    .is-open .main-navigation,
    body.toggled nav.site-navigation,
    body.toggled .main-navigation,
    body.toggled .primary-menu-container,
    /* メニューが展開されたとき直接表示されるケース */
    .main-navigation.toggled,
    .main-navigation.is-open,
    .main-navigation[aria-expanded="true"],
    .site-navigation.open,
    .primary-menu-container.active { display:block !important; }
}

/* ================================================================
   TETSULOG — YouTube Layout × Aorin Theme Colors
   ================================================================ */
:root {
    --yt-bg:           #091524;
    --yt-surface:      #0d1f35;
    --yt-border:       rgba(255,255,255,.1);
    --yt-text:         rgba(255,255,255,.92);
    --yt-muted:        rgba(255,255,255,.5);
    --yt-red:          #00d9ff;
    --yt-chip:         rgba(255,255,255,.07);
    --yt-chip-on:      #00d9ff;
    --yt-chip-on-text: #000;
    --r-thumb: 10px;
    --r-chip:  100px;
    --font: 'Roboto','Noto Sans JP',sans-serif;
    --ease: .18s ease;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

#tl-app{background:var(--yt-bg);min-height:100vh;font-family:var(--font);color:var(--yt-text);}

/* ── トップバー ── */
.tl-topbar{
    position:sticky;top:0;z-index:100;
    background:var(--yt-bg);
    border-bottom:1px solid var(--yt-border);
    height:56px;padding:0 16px;
    display:flex;align-items:center;justify-content:space-between;gap:10px;
}
.tl-logo{font-size:1.05rem;font-weight:700;letter-spacing:.02em;white-space:nowrap;display:flex;align-items:center;gap:6px;flex-shrink:0;}
.tl-logo-icon{color:var(--yt-red);}
.tl-logo-sub{font-size:.72rem;color:var(--yt-muted);background:var(--yt-chip);padding:2px 7px;border-radius:3px;font-weight:500;}

/* ── 検索バー ── */
.tl-search{
    flex:1;max-width:480px;
    position:relative;
    transition:border-color var(--ease),background var(--ease);
}
/* 内側ラッパー：角丸クリッピング */
.tl-search-inner{
    display:flex;align-items:center;
    background:rgba(255,255,255,.06);
    border:1px solid var(--yt-border);
    border-radius:var(--r-chip);
    overflow:hidden;
    transition:border-color var(--ease),background var(--ease);
}
.tl-search:focus-within .tl-search-inner{
    border-color:var(--yt-red);
    background:rgba(255,255,255,.09);
}
.tl-search input{
    flex:1;padding:8px 14px;
    background:transparent;border:none;outline:none;
    color:var(--yt-text);font-family:var(--font);font-size:.88rem;
    min-width:0;
}
.tl-search input::placeholder{color:var(--yt-muted);}
.tl-search-btn{
    width:42px;height:38px;border:none;border-left:1px solid var(--yt-border);
    background:rgba(255,255,255,.06);color:var(--yt-muted);
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    font-size:.88rem;flex-shrink:0;
    transition:background var(--ease),color var(--ease);
}
.tl-search-btn:hover{background:rgba(255,255,255,.12);color:var(--yt-text);}
.tl-search-clear{
    width:32px;height:38px;border:none;
    background:transparent;color:var(--yt-muted);
    cursor:pointer;display:none;align-items:center;justify-content:center;
    font-size:.75rem;flex-shrink:0;
    transition:color var(--ease);
}
.tl-search-clear.show{display:flex;}
.tl-search-clear:hover{color:var(--yt-text);}
/* 検索中の空状態 */
.tl-search-empty{
    display:none;grid-column:1/-1;
    text-align:center;padding:60px 20px;color:var(--yt-muted);
}
.tl-search-empty.show{display:block;}
.tl-search-empty i{font-size:2.5rem;display:block;margin-bottom:12px;color:#2a2a2a;}

/* ── 検索予測候補 ── */
.tl-search-suggest{
    position:absolute;top:calc(100% + 4px);left:0;right:0;
    background:var(--yt-surface);
    border:1px solid rgba(255,255,255,.12);
    border-radius:8px;
    z-index:200;
    overflow:hidden;
    box-shadow:0 8px 24px rgba(0,0,0,.5);
    display:none;
}
.tl-search-suggest.open{ display:block; }
.tl-suggest-item{
    display:flex;align-items:center;gap:10px;
    padding:9px 14px;
    color:var(--yt-text);font-size:.85rem;
    cursor:pointer;transition:background .12s;
    border-bottom:1px solid rgba(255,255,255,.05);
}
.tl-suggest-item:last-child{ border-bottom:none; }
.tl-suggest-item:hover{ background:rgba(255,255,255,.07); }
.tl-suggest-item i{ color:var(--yt-muted);font-size:.75rem;flex-shrink:0; }
.tl-suggest-item mark{
    background:none;color:var(--yt-red);font-weight:700;
}
/* セクションが空のとき非表示 */
.tl-section.hidden{display:none;}

.tl-upload-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 16px;border-radius:var(--r-chip);
    background:var(--yt-red);border:none;
    color:#fff;font-family:var(--font);font-size:.82rem;font-weight:600;
    cursor:pointer;transition:opacity var(--ease);text-decoration:none;white-space:nowrap;flex-shrink:0;
}
.tl-upload-btn:hover{opacity:.85;color:#fff;}
.tl-login-chip{
    display:inline-flex;align-items:center;gap:6px;
    padding:7px 14px;border-radius:var(--r-chip);
    background:transparent;border:1px solid #3ea6ff;
    color:#3ea6ff;font-family:var(--font);font-size:.82rem;font-weight:600;
    cursor:pointer;text-decoration:none;white-space:nowrap;flex-shrink:0;
    transition:background var(--ease);
}
.tl-login-chip:hover{background:rgba(62,166,255,.12);color:#3ea6ff;}
.tl-login-chip .login-text{ }
@media(max-width:600px){
    .tl-login-chip{
        width:36px;height:36px;padding:0;
        border-radius:50%;justify-content:center;
    }
    .tl-login-chip .login-text{ display:none; }
}

/* ── フォローボタン CSS ── */
.tl-short-follow-btn .ic{
    background:rgba(0,217,255,.15);
    color:var(--yt-red);
}
.tl-short-follow-btn.following .ic{
    background:rgba(255,255,255,.15);
    color:rgba(255,255,255,.7);
}
.tl-short-follow-btn:hover .ic{ background:rgba(0,217,255,.28); }
.tl-short-follow-btn.following:hover .ic{ background:rgba(255,255,255,.22); }

/* ── 共有ボタン CSS ── */
.tl-short-share-btn .ic{
    background:rgba(255,255,255,.12);
}
.tl-short-share-btn:hover .ic{ background:rgba(255,255,255,.22); }

/* ── トースト通知 ── */
.tl-toast{
    position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(20px);
    z-index:9999;
    background:rgba(30,30,30,.96);
    border:1px solid rgba(255,255,255,.12);
    border-radius:8px;
    padding:10px 20px;
    color:#fff;font-size:.88rem;font-weight:500;
    pointer-events:none;opacity:0;
    transition:opacity .2s ease,transform .2s ease;
    white-space:nowrap;
    backdrop-filter:blur(10px);
}
.tl-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
.tl-toast i{margin-right:6px;color:var(--yt-red);}

/* ── チップフィルター ── */
.tl-chips{
    position:sticky;top:56px;z-index:99;
    background:var(--yt-bg);
    border-bottom:1px solid var(--yt-border);
    padding:0 20px;height:48px;
    display:flex;align-items:center;gap:8px;
    overflow-x:auto;scrollbar-width:none;
}
.tl-chips::-webkit-scrollbar{display:none;}
.tl-chip{
    display:inline-flex;align-items:center;
    padding:5px 12px;border-radius:var(--r-chip);
    background:var(--yt-chip);border:none;
    color:var(--yt-text);font-family:var(--font);font-size:.83rem;font-weight:500;
    cursor:pointer;white-space:nowrap;flex-shrink:0;
    transition:background var(--ease),color var(--ease);text-decoration:none;
}
.tl-chip:hover{background:#3a3a3a;color:var(--yt-text);}
.tl-chip.on{background:var(--yt-chip-on);color:var(--yt-chip-on-text);}
.tl-chip-sep{width:1px;height:20px;background:var(--yt-border);flex-shrink:0;margin:0 2px;}

/* ── メイン ── */
.tl-main{padding:20px 20px 80px;}

/* ── セクション ── */
.tl-section { margin-bottom: 36px; }
.tl-section-hdr {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--yt-border);
}
.tl-section-hdr-icon {
    width: 32px; height: 32px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
}
.tl-section-hdr-icon.v-portrait { background: rgba(0,217,255,.15); color: var(--yt-red); }
.tl-section-hdr-icon.v-landscape { background: rgba(0,217,255,.1); color: var(--yt-red); }
.tl-section-hdr-icon.img { background: rgba(255,255,255,.08); color: var(--yt-text); }
.tl-section-hdr h2 {
    font-size: .95rem; font-weight: 700;
    color: var(--yt-text); letter-spacing: .03em;
}
.tl-section-hdr-count {
    font-size: .75rem; color: var(--yt-muted);
    background: var(--yt-chip);
    padding: 2px 8px; border-radius: 3px;
}

/* ── グリッド（横動画・画像）── 最大2段 */
.tl-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    grid-template-rows:repeat(2, auto);
    gap:20px 8px;
    overflow:hidden;
}
@media(max-width:1200px){.tl-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:860px) {.tl-grid{grid-template-columns:repeat(2,1fr);}}

/* ── 縦動画グリッド ── 最大2段 */
.tl-grid-portrait {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    grid-template-rows: repeat(2, auto);
    gap: 12px 8px;
    overflow: hidden;
    align-items: start;
}
@media(max-width:1200px){.tl-grid-portrait{grid-template-columns:repeat(5,1fr);}}
@media(max-width:860px) {.tl-grid-portrait{grid-template-columns:repeat(4,1fr);}}

/* ── スマホ：横スクロールカルーセル ── */
@media(max-width:600px){
    /* 横動画・画像 */
    .tl-grid{
        display:flex;
        overflow-x:auto;
        scroll-snap-type:x mandatory;
        -webkit-overflow-scrolling:touch;
        gap:10px;
        padding-bottom:8px;
        scrollbar-width:none;
    }
    .tl-grid::-webkit-scrollbar{display:none;}
    .tl-grid .tl-card{
        flex:0 0 58vw;
        scroll-snap-align:start;
    }

    /* 縦動画 */
    .tl-grid-portrait{
        display:flex;
        overflow-x:auto;
        scroll-snap-type:x mandatory;
        -webkit-overflow-scrolling:touch;
        gap:8px;
        padding-bottom:8px;
        scrollbar-width:none;
    }
    .tl-grid-portrait::-webkit-scrollbar{display:none;}
    .tl-grid-portrait .tl-card{
        flex:0 0 38vw;
        scroll-snap-align:start;
    }

    /* カード5枚目以降はJS側で .tl-card-hidden クラスで隠す（初期） */
    .tl-card.tl-card-hidden{ display:none; }

    /* ○矢印ボタン（カルーセル末尾） */
    .tl-see-all-card{
        flex-shrink:0;
        align-self:center;
        width:40px;height:40px;
        border-radius:50%;
        border:2px solid rgba(255,255,255,.25);
        background:rgba(255,255,255,.08);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;
        color:rgba(255,255,255,.8);
        transition:background .15s, border-color .15s;
        margin-left:4px;
    }
    .tl-see-all-card:hover{ background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.4); }
    .tl-see-all-card i{ font-size:.9rem; }
}

/* ── PC：10件制限 + 前後矢印 ── */
@media(min-width:601px){
    .tl-section-nav{
        display:flex;align-items:center;justify-content:flex-end;
        gap:6px;margin-top:12px;
    }
    .tl-nav-btn{
        width:34px;height:34px;border-radius:50%;
        border:1px solid rgba(255,255,255,.2);
        background:rgba(255,255,255,.06);
        color:rgba(255,255,255,.7);
        display:flex;align-items:center;justify-content:center;
        cursor:pointer;font-size:.8rem;
        transition:background .15s,border-color .15s;
    }
    .tl-nav-btn:hover{ background:rgba(255,255,255,.14); border-color:rgba(255,255,255,.35); }
    .tl-nav-btn:disabled{ opacity:.2; cursor:default; pointer-events:none; }
    .tl-nav-page{ font-size:.72rem;color:rgba(255,255,255,.35);padding:0 4px; }
}
@media(max-width:600px){ .tl-section-nav{ display:none; } }

/* 下部の旧ボタンは非表示 */
.tl-see-all-btn{ display:none; }

/* ── すべて見るフルスクリーンモーダル ── */
.tl-all-modal{
    position:fixed;inset:0;z-index:99999;
    background:var(--yt-bg);
    display:flex;flex-direction:column;
    transform:translateY(100%);
    transition:transform .3s cubic-bezier(.25,.46,.45,.94);
}
.tl-all-modal.open{ transform:translateY(0); }
.tl-all-modal-hdr{
    display:flex;align-items:center;gap:12px;
    padding:12px 16px;
    border-bottom:1px solid rgba(255,255,255,.08);
    flex-shrink:0;
}
.tl-all-modal-back{
    width:36px;height:36px;border-radius:50%;flex-shrink:0;
    border:1px solid rgba(255,255,255,.22);
    background:rgba(255,255,255,.1);
    backdrop-filter:blur(16px) saturate(160%);
    -webkit-backdrop-filter:blur(16px) saturate(160%);
    color:#fff;font-size:.88rem;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    transition:background .15s,transform .15s;
    box-shadow:0 2px 8px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.18);
}
.tl-all-modal-back:hover{ background:rgba(255,255,255,.2); transform:scale(1.07); }
.tl-all-modal-back:active{ transform:scale(.95); }
.tl-all-modal-title{
    font-size:.95rem;font-weight:700;color:#fff;
}
.tl-all-modal-count{
    font-size:.72rem;color:rgba(255,255,255,.4);
    background:rgba(255,255,255,.07);
    padding:2px 8px;border-radius:3px;margin-left:auto;
}
.tl-all-modal-body{
    flex:1;overflow-y:auto;padding:12px;
}
/* モーダル内グリッド */
.tl-all-modal-body .tl-all-grid{
    display:grid;
    gap:10px 8px;
}
.tl-all-modal-body .tl-all-grid.portrait{
    grid-template-columns:repeat(3,1fr);
}
.tl-all-modal-body .tl-all-grid.landscape,
.tl-all-modal-body .tl-all-grid.image{
    grid-template-columns:repeat(2,1fr);
}

/* ── カード ── */
.tl-card{cursor:pointer;position:relative;min-width:0;width:100%;}
.tl-card:hover .tl-thumb-inner{transform:scale(1.03);}

.tl-thumb{
    width:100%;aspect-ratio:16/9;
    border-radius:var(--r-thumb);
    overflow:hidden;background:#1a1a1a;
    position:relative;
    min-width:0;
}
/* 縦動画サムネイルは9/16 */
.tl-card.portrait .tl-thumb {
    aspect-ratio: 9/16;
}
/* 縦カードは情報欄を小さく */
.tl-card.portrait .tl-info { padding: 8px 2px 0; }
.tl-card.portrait .tl-ava  { width: 26px; height: 26px; }
.tl-card.portrait .tl-card-title { font-size: .78rem; }
.tl-card.portrait .tl-card-sub   { font-size: .68rem; }
.tl-thumb-inner{
    width:100%;height:100%;
    transition:transform .28s ease;
    position:relative;
}
.tl-thumb-inner img,
.tl-thumb-inner video{
    width:100%;height:100%;object-fit:cover;display:block;
    position:absolute;inset:0;
}
.tl-thumb-badge{
    position:absolute;bottom:5px;right:6px;
    background:rgba(0,0,0,.85);color:#fff;
    font-size:.7rem;font-weight:700;
    padding:2px 5px;border-radius:3px;
    pointer-events:none;letter-spacing:.03em;
    z-index:2;
}
.tl-thumb-type-badge{
    position:absolute;top:6px;left:6px;
    background:rgba(0,0,0,.72);color:#fff;
    font-size:.65rem;padding:2px 7px;border-radius:3px;
    pointer-events:none;display:flex;align-items:center;gap:3px;
    z-index:2;
}
.tl-thumb-play{
    position:absolute;inset:0;
    display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,0);
    transition:background var(--ease);
    z-index:3;
    pointer-events:none;
}
.tl-card:hover .tl-thumb-play{background:rgba(0,0,0,.2);}
.tl-thumb-play i{
    font-size:2rem;color:rgba(255,255,255,0);
    transition:color var(--ease);
    filter:drop-shadow(0 2px 6px rgba(0,0,0,.6));
}
.tl-card:hover .tl-thumb-play i{color:rgba(255,255,255,.9);}

/* 削除ボタン */
.tl-del{
    position:absolute;top:6px;right:6px;
    width:26px;height:26px;border-radius:50%;
    background:rgba(0,0,0,.72);border:none;
    color:#fff;font-size:.65rem;cursor:pointer;
    display:none;align-items:center;justify-content:center;
    z-index:4;transition:background var(--ease);
}
.tl-card:hover .tl-del.show{display:flex;}
.tl-del:hover{background:var(--yt-red);}

/* カード情報 */
.tl-info{display:flex;gap:10px;padding:10px 2px 0;}
.tl-ava{
    width:34px;height:34px;border-radius:50%;
    overflow:hidden;flex-shrink:0;background:var(--yt-chip);
}
.tl-ava img{width:100%;height:100%;object-fit:cover;display:block;}
.tl-txt{flex:1;min-width:0;}
.tl-card-title{
    font-size:.88rem;font-weight:600;line-height:1.35;
    color:var(--yt-text);
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
    margin-bottom:3px;
}
.tl-card-sub{font-size:.76rem;color:var(--yt-muted);line-height:1.5;}
.tl-card-stats{display:flex;align-items:center;gap:8px;margin-top:2px;}
.tl-like-btn{
    display:inline-flex;align-items:center;gap:3px;
    background:none;border:none;
    color:var(--yt-muted);font-size:.76rem;
    cursor:pointer;padding:0;
    transition:color var(--ease);
}
.tl-like-btn:hover,.tl-like-btn.liked{color:#ff6b6b;}
.tl-like-btn i{font-size:.72rem;}
.tl-card-addon{
    display:inline-flex;align-items:center;gap:3px;
    margin-top:5px;padding:2px 8px;border-radius:3px;
    background:rgba(255,0,0,.12);color:#ff7070;
    font-size:.72rem;font-weight:600;text-decoration:none;
    transition:background var(--ease);
    max-width:100%;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
}
.tl-card-addon:hover{background:rgba(255,0,0,.22);color:#ff9090;}
.tl-card-addon i{font-size:.62rem;flex-shrink:0;}

/* ── 空状態 ── */
.tl-empty{
    grid-column:1/-1;text-align:center;
    padding:80px 20px;color:var(--yt-muted);
}
.tl-empty i{font-size:3.5rem;margin-bottom:18px;display:block;color:#2a2a2a;}

/* ── ページネーション ── */
.tl-pager{display:flex;justify-content:center;gap:4px;margin-top:40px;}
.tl-pg{
    min-width:38px;height:38px;border-radius:4px;
    border:none;background:transparent;
    color:var(--yt-muted);font-size:.88rem;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    text-decoration:none;padding:0 8px;
    transition:background var(--ease);
}
.tl-pg:hover{background:var(--yt-chip);color:var(--yt-text);}
.tl-pg.on{background:var(--yt-chip-on);color:var(--yt-chip-on-text);font-weight:700;}

/* ================================================================
   SHORTS PLAYER — YouTube Shorts 風縦スクロールプレイヤー
   ================================================================ */

/* ── オーバーレイ全体 ── */
.tl-shorts{
    position:fixed;
    /* dvhでアドレスバーを除いた実際の表示領域に合わせる */
    top:0;left:0;right:0;bottom:0;
    width:100%;
    height:100svh; /* small viewport height - アドレスバーを除いた高さ */
    height:100dvh; /* 動的ビューポート（フォールバックは上の行） */
    z-index:9000;
    background:#000;
    opacity:0;pointer-events:none;
    transition:opacity .22s ease;
    display:flex;flex-direction:column;
    /* タッチ操作をコンテナで管理 */
    touch-action:none;
    overflow:hidden;
}
.tl-shorts.open{opacity:1;pointer-events:all;}

/* ── 上部バー（閉じるボタンのみ） ── */
.tl-shorts-bar{
    position:absolute;top:0;left:0;right:0;z-index:10;
    padding:10px 14px;
    display:flex;align-items:center;gap:10px;
    background:linear-gradient(to bottom,rgba(0,0,0,.55) 0%,transparent 100%);
    pointer-events:none;
}
.tl-shorts-bar button,.tl-shorts-bar a{ pointer-events:all; }
.tl-shorts-close{
    width:40px;height:40px;border-radius:50%;
    border:none;background:rgba(0,0,0,.5);
    color:#fff;font-size:1.05rem;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    backdrop-filter:blur(6px);
    transition:background var(--ease);
}
.tl-shorts-close:hover{background:rgba(255,255,255,.2);}
.tl-shorts-home-btn{
    margin-left:auto;
    width:36px;height:36px;border-radius:50%;
    border:none;background:rgba(255,255,255,.1);
    color:#fff;font-size:.9rem;
    display:flex;align-items:center;justify-content:center;
    text-decoration:none;
    backdrop-filter:blur(6px);
    transition:background var(--ease);
    pointer-events:all;
    flex-shrink:0;
}
.tl-shorts-home-btn:hover{background:rgba(255,255,255,.2);color:#fff;}
.tl-shorts-bar-title{
    font-size:.88rem;font-weight:700;color:#fff;
    text-shadow:0 1px 4px rgba(0,0,0,.8);
    letter-spacing:.04em;
}

/* ── スクロールコンテナ（スナップ） ── */
.tl-shorts-scroll{
    width:100%;
    flex:1;
    min-height:0;
    overflow-y:scroll;
    overflow-x:hidden;
    scroll-snap-type:y mandatory;
    scrollbar-width:none;
    /* モバイルブラウザのスムーズスクロールを上書き */
    overscroll-behavior:contain;
    /* タッチ縦スクロールのみ許可 */
    touch-action:pan-y;
    /* iOS慣性スクロール */
    -webkit-overflow-scrolling:touch;
}
.tl-shorts-scroll::-webkit-scrollbar{display:none;}

/* ── 各スライド ── */
.tl-short-slide{
    width:100%;
    height:100%;
    flex-shrink:0;
    scroll-snap-align:start;
    scroll-snap-stop:always;
    position:relative;
    display:flex;align-items:center;justify-content:center;
    background:#000;
    overflow:hidden;
    touch-action:pan-y; /* 縦スクロールのみ、横は防ぐ */
}

/* ── メディア（サイズ変更しない） ── */
.tl-short-media{
    position:absolute;inset:0;
    display:flex;align-items:center;justify-content:center;
}
.tl-short-media video{
    max-width:100%;max-height:100%;
    object-fit:contain;
    display:block;
}
.tl-short-media img{
    max-width:100%;max-height:100%;
    object-fit:contain;
    display:block;
}
/* 画像のとき背景をぼかしてアンビエント表示 */
.tl-short-slide.is-image .tl-short-bg{
    position:absolute;inset:-20px;
    background-size:cover;background-position:center;
    filter:blur(28px) brightness(.35) saturate(1.4);
    transform:scale(1.1);
    z-index:0;
}
.tl-short-slide.is-image .tl-short-media{ z-index:1; }

/* 中央タップで再生/一時停止（動画のみ） */
.tl-short-tap{
    position:absolute;inset:0;z-index:2;cursor:pointer;
    touch-action:manipulation; /* タップの遅延（300ms）を除去 */
}
/* 再生ステートアイコン */
.tl-short-play-ic{
    position:absolute;top:50%;left:50%;
    transform:translate(-50%,-50%) scale(0);
    font-size:4rem;color:rgba(255,255,255,.85);
    pointer-events:none;z-index:3;
    transition:transform .15s ease,opacity .3s ease;
    opacity:0;
    filter:drop-shadow(0 2px 12px rgba(0,0,0,.6));
}
.tl-short-play-ic.show{
    transform:translate(-50%,-50%) scale(1);
    opacity:1;
}

/* ── 右サイドボタン（縦中央） ── */
.tl-short-side{
    position:absolute;right:10px;
    top:50%;transform:translateY(-50%);  /* 縦中央 */
    z-index:7;
    display:flex;flex-direction:column;align-items:center;gap:20px;
}
.tl-short-side-btn{
    display:flex;flex-direction:column;align-items:center;gap:4px;
    background:none;border:none;cursor:pointer;padding:0;color:#fff;
    -webkit-tap-highlight-color:transparent;
}
.tl-short-side-btn .ic{
    width:46px;height:46px;border-radius:50%;
    background:rgba(0,0,0,.5);
    border:1.5px solid rgba(255,255,255,.2);
    display:flex;align-items:center;justify-content:center;
    font-size:1.15rem;
    transition:background var(--ease),transform var(--ease);
}
.tl-short-side-btn:hover .ic{background:rgba(0,0,0,.7);}
.tl-short-side-btn:active .ic{transform:scale(.88);}
.tl-short-side-btn.liked .ic{
    background:rgba(255,68,68,.45);
    border-color:rgba(255,100,100,.6);
}
.tl-short-side-btn.liked .ic i{ color:#ff6b6b; }
.tl-short-side-btn span{
    font-size:.68rem;color:rgba(255,255,255,.95);
    text-shadow:0 1px 4px rgba(0,0,0,.9);font-weight:600;
}
.tl-short-addon-btn .ic{ border-color:rgba(0,217,255,.5); }
.tl-short-addon-btn .ic i{ color:var(--yt-red); }
.tl-short-addon-btn:hover .ic{ background:rgba(0,217,255,.25); }

/* ── フォローバッジ（アバター右下の＋マーク） ── */
.tl-short-ava-wrap{
    position:relative;display:inline-block;flex-shrink:0;
}
.tl-short-ava-link{
    display:block;width:40px;height:40px;border-radius:50%;
    overflow:hidden;
    border:2px solid rgba(255,255,255,.7);
    box-shadow:0 2px 8px rgba(0,0,0,.7);
    text-decoration:none;
    -webkit-tap-highlight-color:transparent;
}
.tl-short-ava{width:100%;height:100%;object-fit:cover;display:block;}
.tl-short-follow-badge{
    position:absolute;bottom:-4px;right:-4px;
    width:20px;height:20px;border-radius:50%;
    background:var(--yt-red);border:2.5px solid #000;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-size:.6rem;color:#000;font-weight:900;
    transition:transform .15s ease,background .15s ease;z-index:10;
    line-height:1;box-shadow:0 2px 6px rgba(0,0,0,.6);
    -webkit-tap-highlight-color:transparent;
}
.tl-short-follow-badge:hover,.tl-short-follow-badge:active{transform:scale(1.25);}
.tl-short-follow-badge.following{background:rgba(255,255,255,.88);}
.tl-short-follow-badge.following i{color:#222;}
.tl-short-follow-badge.hidden{display:none;}

/* ── 下部情報（サイドボタンも包むグラデーション） ── */
.tl-short-info{
    position:absolute;left:0;right:0;bottom:0;
    z-index:6;
    padding:160px 68px 18px 14px;
    background:linear-gradient(to top,
        rgba(0,0,0,.85) 0%,
        rgba(0,0,0,.6)  35%,
        rgba(0,0,0,.25) 65%,
        transparent     100%);
    pointer-events:none;
    transition:opacity .3s ease;
}
/* 再生中はグラデーション非表示 */
.tl-short-slide.playing .tl-short-info {
    background:transparent;
}
/* 展開時はグラデーション復活 */
.tl-short-slide.playing.desc-expanded .tl-short-info {
    background:linear-gradient(to top,
        rgba(0,0,0,.9) 0%,
        rgba(0,0,0,.7) 40%,
        rgba(0,0,0,.3) 70%,
        transparent    100%);
}
/* 情報内のリンク・ボタンだけクリック可能に */
.tl-short-info a,
.tl-short-info button,
.tl-short-ava-wrap{
    pointer-events:all;
}
.tl-short-author{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.tl-short-aname{
    font-size:.88rem;font-weight:700;color:#fff;
    text-shadow:0 1px 4px rgba(0,0,0,.8);
    text-decoration:none;display:block;
}
.tl-short-aname:hover{color:rgba(255,255,255,.75);}
.tl-short-vehicle{
    font-size:.95rem;font-weight:700;color:#fff;
    text-shadow:0 1px 5px rgba(0,0,0,.9);
    margin-bottom:4px;line-height:1.35;
}
.tl-short-sub{font-size:.78rem;color:rgba(255,255,255,.75);text-shadow:0 1px 3px rgba(0,0,0,.8);}
.tl-short-tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;}
.tl-short-tag{font-size:.72rem;color:rgba(255,255,255,.7);text-shadow:0 1px 3px rgba(0,0,0,.8);}

/* ── 戻るボタン（常時表示・Liquid Glass） ── */
.tl-short-back-zone {
    position:absolute;
    /* JSで動的にtopを設定するためデフォルトは0 */
    top:0;left:12px;
    z-index:11;
    pointer-events:none; /* プレイヤーが閉じているときはタッチ無効 */
}
/* プレイヤーが開いているときだけ有効 */
.tl-shorts.open .tl-short-back-zone {
    pointer-events:all;
}
.tl-short-back-btn {
    width:40px;height:40px;border-radius:50%;
    border:1px solid rgba(255,255,255,.25);
    background:rgba(255,255,255,.12);
    backdrop-filter:blur(18px) saturate(160%);
    -webkit-backdrop-filter:blur(18px) saturate(160%);
    color:#fff;font-size:.9rem;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    transition:background .15s, transform .15s, opacity .2s;
    touch-action:manipulation;
    box-shadow:0 2px 12px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.2);
    /* 再生中は非表示、停止中は表示 */
    opacity:0;pointer-events:none;
    margin-top:12px;
}
.tl-short-back-btn.visible {
    opacity:1;pointer-events:all;
}
.tl-short-back-btn:hover { background:rgba(255,255,255,.22); transform:scale(1.08); }
.tl-short-back-btn:active { transform:scale(.95); }
.tl-short-hover-zone {
    position:absolute;left:0;right:0;z-index:5;
    pointer-events:none; /* プレイヤーが閉じているときは無効 */
}
.tl-shorts.open .tl-short-hover-zone {
    pointer-events:all;
}
/* 上帯：高さ60px、コントロールに被らない画面上端のみ */
.tl-short-hover-zone.top-zone {
    top:0;height:60px;
}
/* 下帯：高さ60px、画面下端のみ */
.tl-short-hover-zone.bottom-zone {
    bottom:0;height:60px;
}

.tl-short-arrow{
    position:absolute;left:50%;transform:translateX(-50%);
    z-index:6;width:40px;height:40px;border-radius:50%;
    border:none;background:rgba(0,0,0,.5);
    color:#fff;font-size:.88rem;cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    backdrop-filter:blur(8px);
    opacity:0;pointer-events:none;
    transition:opacity .18s ease, background .12s ease;
}
/* 上ゾーン内の矢印は下端に置く */
.tl-short-hover-zone.top-zone .tl-short-arrow.up{ bottom:8px; }
/* 下ゾーン内の矢印は上端に置く */
.tl-short-hover-zone.bottom-zone .tl-short-arrow.down{ top:8px; }
/* ゾーンホバーで矢印を表示 */
.tl-short-hover-zone:hover .tl-short-arrow{
    opacity:1;pointer-events:all;
}
.tl-short-arrow:hover{ background:rgba(255,255,255,.2); }
.tl-short-arrow:disabled{ opacity:.1 !important; cursor:default; pointer-events:none; }
@media(max-width:600px){ .tl-short-arrow,.tl-short-hover-zone{display:none;} }

/* ── ローディング ── */
.tl-short-loading{
    position:absolute;inset:0;z-index:4;
    display:none;align-items:center;justify-content:center;
    background:rgba(0,0,0,.4);
}
.tl-short-loading.show{ display:flex; }
.tl-short-loading-sp{
    width:36px;height:36px;border-radius:50%;
    border:3px solid rgba(255,255,255,.2);
    border-top-color:var(--yt-red);
    animation:spin .7s linear infinite;
}

/* ──────────────────────────────────────────────────────
   横動画スライド：左に動画・右に情報パネル
   ────────────────────────────────────────────────────── */

/* スライド自体：スナップ対象 ＋ 余白でヘッダー回避 */
.tl-short-slide.is-landscape {
    background:var(--yt-bg);
    align-items:flex-start;
    justify-content:flex-start;
    padding:72px 28px 40px 28px;   /* top:72px = バー高さ60px + 余白12px */
    gap:24px;
    flex-direction:row;
    overflow-y:auto;
    scrollbar-width:thin;
    scrollbar-color:rgba(255,255,255,.15) transparent;
    box-sizing:border-box;
    height:100%;     /* コンテナ基準 */
}
.tl-short-slide.is-landscape::-webkit-scrollbar{width:4px;}
.tl-short-slide.is-landscape::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:2px;}

/* 左：動画カラム */
.tl-ls-video-col {
    flex:1 1 0;
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:0;
    align-self:flex-start;
    max-width:68%;
}

/* 動画ラッパー */
.tl-ls-media {
    width:100%;
    aspect-ratio:16/9;
    background:#000;
    border-radius:10px;
    overflow:hidden;
    position:relative;
    cursor:pointer;
}
.tl-ls-media video {
    width:100%;height:100%;
    object-fit:contain;
    display:block;
}

/* ── カスタムコントロールバー ── */
.tl-ls-controls {
    width:100%;
    background:rgba(9,21,36,.92);
    border-radius:0 0 10px 10px;
    padding:8px 12px 10px;
    display:flex;
    flex-direction:column;
    gap:6px;
    border:1px solid rgba(255,255,255,.07);
    border-top:none;
}
/* プログレスバー */
.tl-ls-progress-wrap {
    position:relative;
    height:16px;          /* クリック領域を確保する固定高さ */
    cursor:pointer;
    display:flex;
    align-items:center;
}
/* 実際のバー（ラッパーの中央に配置） */
.tl-ls-progress-wrap::before {
    content:'';
    position:absolute;left:0;right:0;
    top:50%;transform:translateY(-50%);
    height:3px;
    background:rgba(255,255,255,.15);
    border-radius:2px;
    transition:height .15s;
}
.tl-ls-progress-wrap:hover::before { height:5px; }
.tl-ls-progress {
    position:absolute;left:0;
    top:50%;transform:translateY(-50%);
    height:3px;
    background:var(--yt-red);
    border-radius:2px;
    width:0%;
    pointer-events:none;
    transition:height .15s;
}
.tl-ls-progress-wrap:hover .tl-ls-progress { height:5px; }
.tl-ls-progress-thumb {
    position:absolute;
    top:50%;
    width:13px;height:13px;
    background:var(--yt-red);
    border-radius:50%;
    transform:translate(-50%,-50%);  /* left をJSで設定 */
    opacity:0;
    transition:opacity .15s, transform .1s;
    pointer-events:none;
    box-shadow:0 0 6px rgba(0,217,255,.6);
    z-index:2;
}
.tl-ls-progress-wrap:hover .tl-ls-progress-thumb { opacity:1; }
/* 時間ホバー */
.tl-ls-time-tooltip {
    position:absolute;
    bottom:20px;
    background:rgba(0,0,0,.85);
    color:#fff;
    font-size:.68rem;
    padding:2px 6px;
    border-radius:3px;
    pointer-events:none;
    transform:translateX(-50%);
    white-space:nowrap;
    display:none;
    z-index:3;
}
.tl-ls-progress-wrap:hover .tl-ls-time-tooltip { display:block; }

/* ボタン行 */
.tl-ls-btn-row {
    display:flex;
    align-items:center;
    gap:6px;
}
.tl-ls-btn {
    background:none;border:none;
    color:rgba(255,255,255,.85);
    cursor:pointer;padding:3px;
    display:flex;align-items:center;justify-content:center;
    font-size:.95rem;
    transition:color var(--ease),transform var(--ease);
    flex-shrink:0;
}
.tl-ls-btn:hover { color:#fff; transform:scale(1.12); }
.tl-ls-time {
    font-size:.72rem;
    color:rgba(255,255,255,.6);
    font-family:'Courier New',monospace;
    white-space:nowrap;
    margin-left:2px;
}
.tl-ls-vol-wrap {
    display:flex;align-items:center;gap:5px;
}
.tl-ls-vol-slider {
    -webkit-appearance:none;
    width:60px;height:3px;
    background:rgba(255,255,255,.25);
    border-radius:2px;cursor:pointer;outline:none;
}
.tl-ls-vol-slider::-webkit-slider-thumb {
    -webkit-appearance:none;
    width:11px;height:11px;
    background:var(--yt-red);
    border-radius:50%;
    box-shadow:0 0 4px rgba(0,217,255,.5);
}
.tl-ls-vol-slider::-moz-range-thumb {
    width:11px;height:11px;
    background:var(--yt-red);
    border-radius:50%;border:none;
}
.tl-ls-spacer { flex:1; }
.tl-ls-quality {
    font-size:.65rem;
    color:rgba(255,255,255,.5);
    background:rgba(255,255,255,.08);
    padding:2px 6px;border-radius:3px;
    letter-spacing:.04em;
}

/* 再生オーバーレイ */
.tl-ls-play-overlay {
    position:absolute;inset:0;
    display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,.35);
    z-index:3;
    transition:opacity .2s;
}
.tl-ls-play-overlay i {
    font-size:3.5rem;color:rgba(255,255,255,.9);
    filter:drop-shadow(0 2px 10px rgba(0,0,0,.7));
}
.tl-ls-play-overlay.hidden { opacity:0; pointer-events:none; }

/* 右：情報パネル */
.tl-ls-info-col {
    width:260px;
    flex-shrink:0;
    display:flex;
    flex-direction:column;
    gap:14px;
    align-self:flex-start;
    padding-top:2px;
}
.tl-ls-title {
    font-size:1rem;font-weight:700;
    color:#fff;line-height:1.4;
    word-break:break-word;
}
.tl-ls-series {
    font-size:.78rem;color:rgba(255,255,255,.55);
    margin-top:3px;
}
.tl-ls-author-row {
    display:flex;align-items:center;gap:8px;
    padding:10px 0;
    border-top:1px solid rgba(255,255,255,.08);
    border-bottom:1px solid rgba(255,255,255,.08);
}
.tl-ls-ava {
    width:34px;height:34px;border-radius:50%;
    object-fit:cover;border:2px solid rgba(255,255,255,.3);
    flex-shrink:0;
}
.tl-ls-aname {
    font-size:.85rem;font-weight:600;color:#fff;
    text-decoration:none;flex:1;min-width:0;
    overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
}
.tl-ls-aname:hover{color:var(--yt-red);}
.tl-ls-actions {
    display:flex;flex-direction:column;gap:8px;
}
.tl-ls-action-btn {
    display:flex;align-items:center;justify-content:center;gap:7px;
    padding:9px;border-radius:8px;
    background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.1);
    color:#fff;font-family:var(--font);font-size:.82rem;font-weight:600;
    cursor:pointer;transition:background var(--ease);
    text-decoration:none;
}
.tl-ls-action-btn:hover{background:rgba(255,255,255,.13);color:#fff;}
.tl-ls-action-btn.liked{background:rgba(255,68,68,.2);border-color:rgba(255,100,100,.3);color:#ff6b6b;}
.tl-ls-action-btn.addon{background:rgba(0,217,255,.1);border-color:rgba(0,217,255,.25);color:var(--yt-red);}
.tl-ls-action-btn.addon:hover{background:rgba(0,217,255,.18);}
.tl-ls-desc {
    font-size:.78rem;color:rgba(255,255,255,.65);
    line-height:1.7;word-break:break-word;
    white-space:pre-line;
}
.tl-ls-tags {
    display:flex;flex-wrap:wrap;gap:5px;
}
.tl-ls-tag {
    font-size:.7rem;color:rgba(255,255,255,.5);
    background:rgba(255,255,255,.06);
    padding:2px 7px;border-radius:3px;
}
.tl-ls-meta-row {
    font-size:.72rem;color:rgba(255,255,255,.35);
    display:flex;align-items:center;gap:6px;flex-wrap:wrap;
}
.tl-ls-meta-row span{display:flex;align-items:center;gap:3px;}

/* レスポンシブ（800px以下は縦積み） */
@media(max-width:800px){
    .tl-short-slide.is-landscape{
        flex-direction:column;
        padding:68px 12px 20px;
        gap:12px;
    }
    .tl-ls-video-col{max-width:100%;}
    .tl-ls-info-col{width:100%;}
}

/* ── 関連動画エリア ── */
.tl-ls-related {
    margin-top:20px;
    border-top:1px solid rgba(255,255,255,.08);
    padding-top:16px;
}
.tl-ls-related-title {
    font-size:.75rem;font-weight:700;
    color:rgba(255,255,255,.4);
    letter-spacing:.08em;text-transform:uppercase;
    margin-bottom:10px;
}
.tl-ls-related-list {
    display:flex;flex-direction:column;gap:10px;
}
.tl-ls-related-item {
    display:flex;gap:10px;
    cursor:pointer;border-radius:7px;
    padding:6px;
    transition:background var(--ease);
}
.tl-ls-related-item:hover { background:rgba(255,255,255,.07); }
.tl-ls-related-thumb {
    width:90px;height:52px;
    border-radius:5px;overflow:hidden;
    flex-shrink:0;background:#1a1a1a;
    position:relative;
}
.tl-ls-related-thumb img,
.tl-ls-related-thumb video {
    width:100%;height:100%;object-fit:cover;display:block;
}
.tl-ls-related-thumb .r-badge {
    position:absolute;top:3px;left:3px;
    background:rgba(0,0,0,.75);color:#fff;
    font-size:.55rem;font-weight:700;
    padding:1px 5px;border-radius:2px;
}
.tl-ls-related-info { flex:1;min-width:0; }
.tl-ls-related-vtitle {
    font-size:.78rem;font-weight:600;color:#fff;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
    overflow:hidden;line-height:1.35;margin-bottom:3px;
}
.tl-ls-related-meta {
    font-size:.68rem;color:rgba(255,255,255,.4);
    display:flex;align-items:center;gap:5px;
}

/* ── 説明の折りたたみ ── */
.tl-short-desc {
    font-size:.82rem;
    color:rgba(255,255,255,.8);
    line-height:1.6;
    margin-top:6px;
    text-shadow:0 1px 3px rgba(0,0,0,.8);
    word-break:break-word;
    white-space:pre-line;
}
.tl-short-desc.collapsed {
    display:-webkit-box;
    -webkit-line-clamp:1;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
.tl-short-desc-toggle {
    display:inline-block;
    margin-top:4px;
    font-size:.75rem;
    color:rgba(255,255,255,.55);
    cursor:pointer;
    background:none;border:none;
    padding:0;pointer-events:all;
    text-shadow:0 1px 3px rgba(0,0,0,.8);
}
.tl-short-desc-toggle:hover { color:rgba(255,255,255,.85); }

/* ================================================================
   UPLOAD MODAL
   ================================================================ */
.tl-mwrap{
    position:fixed;inset:0;z-index:9100;
    display:flex;align-items:center;justify-content:center;padding:20px;
    background:rgba(0,0,0,.78);
    opacity:0;pointer-events:none;transition:opacity .2s ease;
}
.tl-mwrap.open{opacity:1;pointer-events:all;}
.tl-modal{
    width:100%;max-width:540px;
    background:var(--yt-surface);border-radius:10px;
    padding:22px;max-height:90vh;overflow-y:auto;
    transform:translateY(14px) scale(.97);
    transition:transform .2s ease;
}
.tl-mwrap.open .tl-modal{transform:none;}
.tl-mhdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
.tl-mtitle{font-size:.98rem;font-weight:700;display:flex;align-items:center;gap:8px;}
.tl-mtitle i{color:var(--yt-red);}
.tl-mx{
    width:30px;height:30px;border-radius:50%;
    border:none;background:rgba(255,255,255,.1);
    color:var(--yt-text);cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    font-size:.88rem;transition:background var(--ease);
}
.tl-mx:hover{background:rgba(255,255,255,.18);}
.tl-type-row{display:flex;border:1px solid var(--yt-border);border-radius:6px;overflow:hidden;margin-bottom:16px;}
.tl-topt{
    flex:1;padding:9px;border:none;background:transparent;
    color:var(--yt-muted);font-family:var(--font);font-size:.83rem;font-weight:600;
    cursor:pointer;transition:all var(--ease);
    display:flex;align-items:center;justify-content:center;gap:6px;
}
.tl-topt.on{background:var(--yt-red);color:#fff;}
.tl-drop{
    border:2px dashed var(--yt-border);border-radius:8px;
    padding:28px 14px;text-align:center;cursor:pointer;
    background:rgba(255,255,255,.02);margin-bottom:16px;
    transition:all var(--ease);position:relative;
}
.tl-drop:hover,.tl-drop.dz{border-color:var(--yt-red);background:rgba(255,0,0,.04);}
.tl-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;}
.tl-drop-ic{font-size:2rem;color:#3a3a3a;margin-bottom:8px;}
.tl-drop-tx{font-size:.88rem;color:var(--yt-muted);}
.tl-drop-hint{font-size:.73rem;color:#4a4a4a;margin-top:4px;}
#tl-prev-wrap{display:none;margin-bottom:12px;border-radius:6px;overflow:hidden;}
#tl-prev-img,#tl-prev-vid{width:100%;max-height:190px;object-fit:cover;display:block;border-radius:6px;}
.tl-f{margin-bottom:12px;}
.tl-f label{display:block;font-size:.76rem;font-weight:600;color:var(--yt-muted);margin-bottom:4px;}
.tl-f label .r{color:var(--yt-red);margin-left:2px;}
.tl-inp,.tl-sel{
    width:100%;padding:9px 11px;border-radius:5px;
    border:1px solid var(--yt-border);background:rgba(255,255,255,.05);
    color:var(--yt-text);font-family:var(--font);font-size:.88rem;
    outline:none;transition:border-color var(--ease);
}
.tl-inp:focus,.tl-sel:focus{border-color:#3ea6ff;}
.tl-sel option{background:var(--yt-surface);}
.tl-frow{display:flex;gap:8px;}
.tl-frow .tl-f{flex:1;}
.tl-alert{padding:9px 12px;border-radius:5px;font-size:.8rem;display:none;margin-bottom:12px;}
.tl-alert.err{background:rgba(255,68,68,.14);border:1px solid rgba(255,68,68,.28);color:#ff9090;}
.tl-alert.ok{background:rgba(0,200,100,.1);border:1px solid rgba(0,200,100,.22);color:#80ffb0;}
.tl-sub{
    width:100%;padding:11px;background:var(--yt-red);
    border:none;border-radius:5px;color:#fff;
    font-family:var(--font);font-size:.92rem;font-weight:700;
    cursor:pointer;transition:opacity var(--ease);
    display:flex;align-items:center;justify-content:center;gap:7px;margin-top:14px;
}
.tl-sub:hover{opacity:.88;}
.tl-sub:disabled{opacity:.4;cursor:not-allowed;}
.tl-sub .sp{width:15px;height:15px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.tl-login-note{text-align:center;padding:20px;color:var(--yt-muted);font-size:.88rem;}
.tl-login-note a{color:#3ea6ff;text-decoration:none;}

/* ── アドオン選択UI ── */
.tl-addon-sel{
    display:flex;align-items:center;gap:6px;
    width:100%;padding:8px 10px;border-radius:5px;
    border:1px solid var(--yt-border);background:rgba(255,255,255,.05);
    color:var(--yt-text);font-family:var(--font);font-size:.85rem;
    cursor:pointer;transition:border-color var(--ease);text-align:left;
    min-height:36px;
}
.tl-addon-sel:hover{border-color:rgba(255,255,255,.3);}
.tl-addon-sel .placeholder{color:var(--yt-muted);}
.tl-addon-sel .selected-title{
    flex:1;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
    color:var(--yt-text);
}
/* placeholderクラスが両方ついている未選択状態はグレーに */
.tl-addon-sel .selected-title.placeholder{color:var(--yt-muted);}
.tl-addon-sel .sel-clear{
    margin-left:auto;flex-shrink:0;
    background:none;border:none;color:var(--yt-muted);
    cursor:pointer;font-size:.75rem;padding:0 2px;
    display:none;
}
.tl-addon-sel .sel-clear.show{display:block;}

/* アドオン検索モーダル */
.tl-addon-modal-wrap{
    position:fixed;inset:0;z-index:9200;
    display:flex;align-items:center;justify-content:center;padding:20px;
    background:rgba(0,0,0,.85);
    opacity:0;pointer-events:none;transition:opacity .2s ease;
}
.tl-addon-modal-wrap.open{opacity:1;pointer-events:all;}
.tl-addon-modal{
    width:100%;max-width:520px;
    background:var(--yt-surface);border-radius:10px;
    padding:20px;max-height:85vh;
    display:flex;flex-direction:column;gap:12px;
    transform:translateY(10px) scale(.97);
    transition:transform .2s ease;
}
.tl-addon-modal-wrap.open .tl-addon-modal{transform:none;}
.tl-addon-mhdr{display:flex;align-items:center;justify-content:space-between;}
.tl-addon-mtitle{font-size:.95rem;font-weight:700;color:var(--yt-text);}
.tl-addon-tabs{display:flex;border:1px solid var(--yt-border);border-radius:6px;overflow:hidden;}
.tl-addon-tab{
    flex:1;padding:8px;border:none;background:transparent;
    color:var(--yt-muted);font-family:var(--font);font-size:.82rem;font-weight:600;
    cursor:pointer;transition:all var(--ease);
    display:flex;align-items:center;justify-content:center;gap:5px;
}
.tl-addon-tab.on{background:rgba(255,255,255,.1);color:var(--yt-text);}
.tl-addon-search-row{
    display:flex;gap:6px;
}
.tl-addon-search-row input{
    flex:1;padding:8px 10px;border-radius:5px;
    border:1px solid var(--yt-border);background:rgba(255,255,255,.05);
    color:var(--yt-text);font-family:var(--font);font-size:.85rem;
    outline:none;
}
.tl-addon-search-row input:focus{border-color:var(--yt-red);}
.tl-addon-search-btn{
    padding:8px 14px;border-radius:5px;background:var(--yt-red);
    border:none;color:#fff;font-family:var(--font);font-size:.82rem;
    font-weight:600;cursor:pointer;white-space:nowrap;
    transition:opacity var(--ease);
}
.tl-addon-search-btn:hover{opacity:.85;}
.tl-addon-list{
    flex:1;overflow-y:auto;
    display:flex;flex-direction:column;gap:4px;
    max-height:340px;scrollbar-width:thin;
    scrollbar-color:rgba(255,255,255,.15) transparent;
}
.tl-addon-item{
    display:flex;align-items:center;gap:10px;
    padding:9px 10px;border-radius:6px;
    border:1px solid transparent;
    background:rgba(255,255,255,.04);
    cursor:pointer;transition:all var(--ease);
}
.tl-addon-item:hover{background:rgba(255,255,255,.09);border-color:rgba(255,255,255,.1);}
.tl-addon-item img{
    width:44px;height:44px;border-radius:5px;
    object-fit:cover;flex-shrink:0;background:#1a1a1a;
}
.tl-addon-item-body{flex:1;min-width:0;}
.tl-addon-item-title{
    font-size:.85rem;font-weight:600;color:var(--yt-text);
    overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
}
.tl-addon-item-meta{font-size:.72rem;color:var(--yt-muted);margin-top:2px;}
.tl-addon-empty{
    text-align:center;padding:30px;
    color:var(--yt-muted);font-size:.85rem;
}
.tl-addon-loading{
    text-align:center;padding:20px;color:var(--yt-muted);
}
.tl-addon-loading .sp{
    display:inline-block;width:20px;height:20px;
    border:2px solid rgba(255,255,255,.2);border-top-color:var(--yt-red);
    border-radius:50%;animation:spin .7s linear infinite;
}

/* card animate */
.tl-card{animation:ci .3s ease both;}
.tl-card:nth-child(2n){animation-delay:.04s;}
.tl-card:nth-child(3n){animation-delay:.08s;}
.tl-card:nth-child(4n){animation-delay:.12s;}
@keyframes ci{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;}}
</style>
<?php
/**
 * PART 2/3 — トップバー・チップ・グリッド・ライトボックスHTML
 * Part1のコードの続きに結合してください
 */
?>

<div id="tl-app">

<!-- ================================================================
     トップバー
     ================================================================ -->
<header class="tl-topbar">
    <div class="tl-logo">
        <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/assets/images/tetulog.png'); ?>"
             alt="鉄ログ"
             style="height:44px;width:auto;display:block;object-fit:contain;">
    </div>

    <!-- 検索バー -->
    <div class="tl-search">
        <div class="tl-search-inner">
        <input type="text" id="tl-search-input"
               placeholder="車両名・作者・タグで検索..."
               autocomplete="off" spellcheck="false">
        <button class="tl-search-clear" id="tl-search-clear" aria-label="クリア">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <button class="tl-search-btn" aria-label="検索">
            <i class="fa-solid fa-magnifying-glass"></i>
        </button>
        </div>
        <div class="tl-search-suggest" id="tl-search-suggest"></div>
    </div>

    <?php if ($logged): ?>
    <button class="tl-upload-btn" id="tl-open-modal">
        <i class="fa-solid fa-video"></i> 投稿する
    </button>
    <?php else: ?>
    <a href="<?php echo esc_url(site_url('/login/')); ?>" class="tl-login-chip">
        <i class="fa-solid fa-right-to-bracket"></i>
        <span class="login-text">ログイン</span>
    </a>
    <?php endif; ?>
</header>

<!-- ================================================================
     チップフィルター
     ================================================================ -->
<div class="tl-chips">
    <a href="?<?php echo http_build_query(array_merge($_GET,['sort'=>'new','type'=>'','cat'=>'','paged'=>1])); ?>"
       class="tl-chip <?php echo $filter_sort==='new' && !$filter_type && !$filter_category ? 'on' : ''; ?>">すべて</a>

    <div class="tl-chip-sep"></div>

    <a href="?<?php echo http_build_query(array_merge($_GET,['type'=>'video','paged'=>1])); ?>"
       class="tl-chip <?php echo $filter_type==='video' ? 'on' : ''; ?>">
        <i class="fa-solid fa-play" style="font-size:.62rem;"></i> 動画
    </a>
    <a href="?<?php echo http_build_query(array_merge($_GET,['type'=>'image','paged'=>1])); ?>"
       class="tl-chip <?php echo $filter_type==='image' ? 'on' : ''; ?>">
        <i class="fa-solid fa-image" style="font-size:.62rem;"></i> 画像
    </a>

    <div class="tl-chip-sep"></div>

    <?php foreach ($cats as $cat): ?>
    <a href="?<?php echo http_build_query(array_merge($_GET,['cat'=>$cat,'paged'=>1])); ?>"
       class="tl-chip <?php echo $filter_category===$cat ? 'on' : ''; ?>">
        <?php echo esc_html($cat); ?>
    </a>
    <?php endforeach; ?>

    <div class="tl-chip-sep"></div>

    <a href="?<?php echo http_build_query(array_merge($_GET,['sort'=>'likes','paged'=>1])); ?>"
       class="tl-chip <?php echo $filter_sort==='likes' ? 'on' : ''; ?>">
        <i class="fa-solid fa-fire" style="font-size:.62rem;color:#ff7043;"></i> 人気順
    </a>

    <?php if ($filter_category || $filter_type || $filter_sort !== 'new'): ?>
    <div class="tl-chip-sep"></div>
    <a href="?" class="tl-chip" style="color:var(--yt-muted);">
        <i class="fa-solid fa-xmark" style="font-size:.65rem;"></i> リセット
    </a>
    <?php endif; ?>
</div>

<!-- ================================================================
     グリッド
     ================================================================ -->
<main class="tl-main">
<?php
// カードHTMLを出力するヘルパー
function tl_render_card($pid, $logged, $uid, $extra_class = '') {
    $d = tl_get_card_data($pid, $logged, $uid);
    extract($d);
    $del_nonce_attr = esc_attr($del_nonce);
    ob_start();
?>
<div class="tl-card <?php echo esc_attr($extra_class); ?>"
     data-pid="<?php echo $pid; ?>"
     data-type="<?php echo esc_attr($mtype); ?>"
     data-orient="<?php echo esc_attr($orient); ?>"
     data-url="<?php echo esc_url($media_url); ?>"
     data-vehicle="<?php echo esc_attr($vehicle); ?>"
     data-series="<?php echo esc_attr($series); ?>"
     data-category="<?php echo esc_attr($category); ?>"
     data-tags="<?php echo esc_attr($tl_tags); ?>"
     data-desc="<?php echo esc_attr($tl_desc); ?>"
     data-addon-id="<?php echo $addon_id; ?>"
     data-addon-title="<?php echo esc_attr($addon_title); ?>"
     data-addon-url="<?php echo esc_url($addon_url); ?>"
     data-addon-thumb="<?php echo esc_url($addon_thumb); ?>"
     data-likes="<?php echo $likes; ?>"
     data-views="<?php echo $views; ?>"
     data-liked="<?php echo $is_liked ? '1' : '0'; ?>"
     data-author="<?php echo esc_attr($author_name); ?>"
     data-author-id="<?php echo esc_attr($author_id); ?>"
     data-author-url="<?php echo esc_url($author_url); ?>"
     data-avatar="<?php echo esc_url($author_avatar); ?>"
     data-date="<?php echo esc_attr($time_ago); ?>">

    <div class="tl-thumb" onclick="tlOpenLb(this.closest('.tl-card'))">
        <div class="tl-thumb-inner">
            <?php if ($media_url): ?>
                <?php if ($mtype === 'video'): ?>
                <video data-src="<?php echo esc_url($media_url); ?>"
                       muted playsinline preload="none"></video>
                <?php else: ?>
                <img src="<?php echo esc_url($media_url); ?>"
                     alt="<?php echo esc_attr($vehicle); ?>" loading="lazy">
                <?php endif; ?>
            <?php else: ?>
            <div style="width:100%;height:100%;background:#1a1a1a;display:flex;align-items:center;justify-content:center;position:absolute;inset:0;">
                <i class="fa-solid fa-train" style="color:#333;font-size:2rem;"></i>
            </div>
            <?php endif; ?>

            <?php if ($mtype === 'video'): ?>
            <div class="tl-thumb-play">
                <i class="fa-solid fa-circle-play"></i>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($is_owner || current_user_can('manage_options')): ?>
        <button class="tl-del show"
                data-pid="<?php echo $pid; ?>"
                data-nonce="<?php echo $del_nonce_attr; ?>"
                onclick="event.stopPropagation();tlDelete(this);"
                title="削除">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <?php endif; ?>
    </div>

    <div class="tl-info" onclick="tlOpenLb(this.closest('.tl-card'))">
        <div class="tl-ava">
            <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>">
        </div>
        <div class="tl-txt">
            <div class="tl-card-title"><?php echo esc_html($vehicle); ?></div>
            <div class="tl-card-sub">
                <div><?php echo esc_html($author_name); ?></div>
                <div class="tl-card-stats">
                    <button class="tl-like-btn <?php echo $is_liked ? 'liked' : ''; ?>"
                            data-pid="<?php echo $pid; ?>"
                            onclick="event.stopPropagation();tlLike(this);">
                        <i class="fa-<?php echo $is_liked ? 'solid' : 'regular'; ?> fa-heart"></i>
                        <span class="lc"><?php echo $likes; ?></span>
                    </button>
                    <span style="display:flex;align-items:center;gap:2px;font-size:.7rem;">
                        <i class="fa-regular fa-eye" style="font-size:.68rem;"></i><?php echo number_format($views); ?>
                    </span>
                    <span><?php echo esc_html($time_ago); ?></span>
                </div>
            </div>
            <?php if ($addon_title): ?>
            <a class="tl-card-addon"
               href="<?php echo esc_url($addon_url); ?>"
               target="_blank" rel="noopener"
               onclick="event.stopPropagation();"
               title="<?php echo esc_attr($addon_title); ?>">
                <?php if ($addon_thumb): ?>
                <img src="<?php echo esc_url($addon_thumb); ?>"
                     alt="<?php echo esc_attr($addon_title); ?>"
                     style="width:16px;height:16px;border-radius:4px;object-fit:cover;flex-shrink:0;">
                <?php else: ?>
                <i class="fa-solid fa-cube"></i>
                <?php endif; ?>
                <?php echo esc_html($addon_title); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php
    return ob_get_clean();
}
?>

<!-- ================================================================
     セクション1: 縦動画
     ================================================================ -->
<?php if (!empty($sec_portrait)): ?>
<div class="tl-section" id="tl-section-portrait">
    <div class="tl-section-hdr">
        <div class="tl-section-hdr-icon v-portrait">
            <i class="fa-solid fa-mobile-alt"></i>
        </div>
        <h2>縦動画 Shorts</h2>
        <span class="tl-section-hdr-count"><?php echo count($sec_portrait); ?></span>
    </div>
    <div class="tl-grid tl-grid-portrait" id="tl-grid-portrait">
        <?php foreach ($sec_portrait as $pid): ?>
            <?php echo tl_render_card($pid, $logged, $uid, 'portrait'); ?>
        <?php endforeach; ?>
    </div>
    <div class="tl-section-nav" id="tl-nav-portrait">
        <span class="tl-nav-page" id="tl-nav-page-portrait"></span>
        <button class="tl-nav-btn" id="tl-nav-prev-portrait" onclick="tlNavPage('portrait',-1)"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="tl-nav-btn" id="tl-nav-next-portrait" onclick="tlNavPage('portrait',1)"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <button class="tl-see-all-btn" onclick="tlOpenAllModal('portrait','縦動画 Shorts')">
        <i class="fa-solid fa-border-all" style="margin-right:5px;"></i>縦動画をすべて見る（<?php echo count($sec_portrait); ?>件）<i class="fa-solid fa-chevron-right" style="margin-left:5px;font-size:.75rem;"></i>
    </button>
</div>
<?php endif; ?>

<!-- ================================================================
     セクション2: 画像
     ================================================================ -->
<?php if (!empty($sec_image)): ?>
<div class="tl-section" id="tl-section-image">
    <div class="tl-section-hdr">
        <div class="tl-section-hdr-icon img">
            <i class="fa-solid fa-image"></i>
        </div>
        <h2>画像</h2>
        <span class="tl-section-hdr-count"><?php echo count($sec_image); ?></span>
    </div>
    <div class="tl-grid" id="tl-grid-image">
        <?php foreach ($sec_image as $pid): ?>
            <?php echo tl_render_card($pid, $logged, $uid, ''); ?>
        <?php endforeach; ?>
    </div>
    <div class="tl-section-nav" id="tl-nav-image">
        <span class="tl-nav-page" id="tl-nav-page-image"></span>
        <button class="tl-nav-btn" id="tl-nav-prev-image" onclick="tlNavPage('image',-1)"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="tl-nav-btn" id="tl-nav-next-image" onclick="tlNavPage('image',1)"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <button class="tl-see-all-btn" onclick="tlOpenAllModal('image','画像')">
        <i class="fa-solid fa-border-all" style="margin-right:5px;"></i>画像をすべて見る（<?php echo count($sec_image); ?>件）<i class="fa-solid fa-chevron-right" style="margin-left:5px;font-size:.75rem;"></i>
    </button>
</div>
<?php endif; ?>

<!-- ================================================================
     セクション3: 横動画
     ================================================================ -->
<?php if (!empty($sec_landscape)): ?>
<div class="tl-section" id="tl-section-landscape">
    <div class="tl-section-hdr">
        <div class="tl-section-hdr-icon v-landscape">
            <i class="fa-solid fa-video"></i>
        </div>
        <h2>横動画</h2>
        <span class="tl-section-hdr-count"><?php echo count($sec_landscape); ?></span>
    </div>
    <div class="tl-grid" id="tl-grid-landscape">
        <?php foreach ($sec_landscape as $pid): ?>
            <?php echo tl_render_card($pid, $logged, $uid, ''); ?>
        <?php endforeach; ?>
    </div>
    <div class="tl-section-nav" id="tl-nav-landscape">
        <span class="tl-nav-page" id="tl-nav-page-landscape"></span>
        <button class="tl-nav-btn" id="tl-nav-prev-landscape" onclick="tlNavPage('landscape',-1)"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="tl-nav-btn" id="tl-nav-next-landscape" onclick="tlNavPage('landscape',1)"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <button class="tl-see-all-btn" onclick="tlOpenAllModal('landscape','横動画')">
        <i class="fa-solid fa-border-all" style="margin-right:5px;"></i>横動画をすべて見る（<?php echo count($sec_landscape); ?>件）<i class="fa-solid fa-chevron-right" style="margin-left:5px;font-size:.75rem;"></i>
    </button>
</div>
<?php endif; ?>

<?php if (empty($sec_portrait) && empty($sec_image) && empty($sec_landscape)): ?>
<div class="tl-empty">
    <i class="fa-solid fa-train"></i>
    <h3>まだ投稿がありません</h3>
    <p style="margin-top:8px;font-size:.88rem;">最初の鉄ログを投稿しましょう！</p>
</div>
<?php endif; ?>


</main><!-- /.tl-main -->

<!-- ================================================================
     ライトボックス
     ================================================================ -->
<!-- ================================================================
     SHORTS PLAYER（YouTube Shorts 風縦スクロール）
     ================================================================ -->
<div class="tl-shorts" id="tl-shorts">

    <!-- 上部バー -->
    <div class="tl-shorts-bar">
        <button class="tl-shorts-close" id="tl-shorts-close" aria-label="閉じる">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <span class="tl-shorts-bar-title">鉄ログ</span>
    </div>

    <!-- 左上ホバーゾーン：戻るボタン -->
    <div class="tl-short-back-zone" id="tl-short-back-zone">
        <button class="tl-short-back-btn" id="tl-short-back-btn" aria-label="一覧に戻る">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
    </div>

    <!-- 上ホバーゾーン（カーソルを当てると↑矢印が出る） -->
    <div class="tl-short-hover-zone top-zone">
        <button class="tl-short-arrow up" id="tl-arr-up" aria-label="前へ">
            <i class="fa-solid fa-chevron-up"></i>
        </button>
    </div>

    <!-- 下ホバーゾーン（カーソルを当てると↓矢印が出る） -->
    <div class="tl-short-hover-zone bottom-zone">
        <button class="tl-short-arrow down" id="tl-arr-down" aria-label="次へ">
            <i class="fa-solid fa-chevron-down"></i>
        </button>
    </div>

    <!-- スナップスクロールコンテナ（動的生成） -->
    <div class="tl-shorts-scroll" id="tl-shorts-scroll"></div>

</div>
<?php
/**
 * PART 3/3 — 投稿モーダルHTML + JavaScript全体
 * Part1・Part2の続きに結合してください
 */
?>

<!-- ================================================================
     投稿モーダル
     ================================================================ -->
<div class="tl-mwrap" id="tl-modal">
<div class="tl-modal">

    <div class="tl-mhdr">
        <div class="tl-mtitle">
            <i class="fa-solid fa-video"></i> 鉄ログを投稿
        </div>
        <button class="tl-mx" id="tl-modal-close" aria-label="閉じる">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <?php if ($logged): ?>

    <div class="tl-alert" id="tl-alert"></div>

    <!-- タイプ選択 -->
    <div class="tl-type-row">
        <button class="tl-topt on" data-type="image">
            <i class="fa-solid fa-image"></i> 画像
        </button>
        <button class="tl-topt" data-type="video">
            <i class="fa-solid fa-video"></i> 動画 (30秒以内)
        </button>
    </div>
    <input type="hidden" id="tl-sel-type" value="image">

    <!-- ドロップゾーン -->
    <div class="tl-drop" id="tl-drop">
        <input type="file" id="tl-file" accept="image/*">
        <div class="tl-drop-ic"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div class="tl-drop-tx">クリックまたはドラッグ＆ドロップ</div>
        <div class="tl-drop-hint" id="tl-file-hint">JPG / PNG / WebP / GIF &bull; 最大 10MB</div>
    </div>
    <div id="tl-prev-wrap">
        <img id="tl-prev-img" alt="">
        <video id="tl-prev-vid" controls muted></video>
    </div>

    <!-- フォーム -->
    <form id="tl-form">
        <?php wp_nonce_field('submit_tetsulog_post', 'tl_nonce'); ?>
        <!-- 編集モード時は post_id をセット -->
        <input type="hidden" name="edit_post_id" id="tl-edit-post-id" value="">

        <div class="tl-f">
            <label>車両名 <span class="r">*</span></label>
            <input type="text" class="tl-inp" name="vehicle_name"
                   placeholder="例: E5系新幹線 はやぶさ" required>
        </div>

        <div class="tl-frow">
            <div class="tl-f">
                <label>カテゴリー</label>
                <select class="tl-sel" name="tetsulog_category">
                    <option value="">未選択</option>
                    <?php foreach ($cats as $c): ?>
                    <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="tl-f">
                <label>アドオンリンク <span style="font-weight:400;color:#555;">(任意)</span></label>
                <input type="hidden" name="linked_addon_id" id="tl-addon-id-val" value="">
                <div style="display:flex;align-items:center;gap:6px;">
                    <button type="button" class="tl-addon-sel" id="tl-addon-sel-btn"
                            style="color:#fff;flex:1;">
                        <i class="fa-solid fa-cube" style="color:rgba(255,255,255,.4);flex-shrink:0;font-size:.8rem;"></i>
                        <span class="placeholder selected-title" style="color:rgba(255,255,255,.4);">投稿を選択...</span>
                    </button>
                    <button type="button" id="tl-addon-clear"
                            style="display:none;padding:4px 8px;border-radius:5px;border:none;
                                   background:rgba(255,255,255,.1);color:rgba(255,255,255,.7);
                                   cursor:pointer;font-size:.8rem;flex-shrink:0;">✕</button>
                </div>
            </div>
        </div>

        <div class="tl-f">
            <label>タグ <span style="font-weight:400;color:#555;">(スペース区切り)</span></label>
            <input type="text" class="tl-inp" name="tetsulog_tags"
                   placeholder="例: 新幹線 流線型 青">
        </div>

        <div class="tl-f">
            <label>説明</label>
            <textarea class="tl-inp" name="tetsulog_description"
                      rows="3"
                      placeholder="車両の説明や走行シーンの補足など..."
                      style="resize:vertical;line-height:1.6;"></textarea>
        </div>
    </form>

    <button class="tl-sub" id="tl-submit">
        <i class="fa-solid fa-paper-plane"></i> 投稿する
    </button>

    <?php else: ?>
    <div class="tl-login-note">
        <i class="fa-brands fa-discord" style="font-size:2rem;color:var(--yt-red);display:block;margin-bottom:12px;"></i>
        投稿するには<a href="<?php echo esc_url(site_url('/login/')); ?>">ログイン</a>が必要です
    </div>
    <?php endif; ?>

</div>
</div><!-- /.tl-mwrap -->

</div><!-- /#tl-app -->

<!-- ================================================================
     スマホ用「すべて見る」フルスクリーンモーダル
     ================================================================ -->
<div class="tl-all-modal" id="tl-all-modal">
    <div class="tl-all-modal-hdr">
        <button class="tl-all-modal-back" onclick="tlCloseAllModal()">
            <i class="fa-solid fa-arrow-left"></i>
        </button>
        <div class="tl-all-modal-title" id="tl-all-modal-title">すべて見る</div>
        <div class="tl-all-modal-count" id="tl-all-modal-count"></div>
    </div>
    <div class="tl-all-modal-body" id="tl-all-modal-body"></div>
</div>

<!-- ================================================================
     アドオン選択モーダル（#tl-app外・body直下で干渉なし）
     ================================================================ -->
<div class="tl-addon-modal-wrap" id="tl-addon-modal-wrap">
<div class="tl-addon-modal">
    <div class="tl-addon-mhdr">
        <div class="tl-addon-mtitle"><i class="fa-solid fa-cube" style="color:var(--yt-red);margin-right:6px;"></i>アドオンを選択</div>
        <button type="button" class="tl-mx" id="tl-addon-modal-close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- タブ -->
    <div class="tl-addon-tabs">
        <button type="button" class="tl-addon-tab on" id="tl-atab-own" data-tab="own">
            <i class="fa-solid fa-user"></i> 自分の投稿
        </button>
        <button type="button" class="tl-addon-tab" id="tl-atab-search" data-tab="search">
            <i class="fa-solid fa-magnifying-glass"></i> 投稿を検索
        </button>
    </div>

    <!-- 検索行 -->
    <div id="tl-addon-search-row" class="tl-addon-search-row" style="display:none;">
        <input type="text" id="tl-addon-search-input" placeholder="タイトルで検索...">
        <button type="button" class="tl-addon-search-btn" id="tl-addon-search-exec">
            <i class="fa-solid fa-magnifying-glass"></i> 検索
        </button>
    </div>

    <!-- 結果リスト -->
    <div class="tl-addon-list" id="tl-addon-list">
        <div class="tl-addon-loading"><div class="sp"></div></div>
    </div>
</div>
</div>

<!-- ================================================================
     JavaScript
     ================================================================ -->
<script>
(function(){
'use strict';

/* ── 定数 ── */
const AJAX   = '<?php echo admin_url('admin-ajax.php'); ?>';
const NONCE  = '<?php echo esc_js($tl_nonce); ?>';
const LOGGED = <?php echo $logged ? 'true' : 'false'; ?>;
const CURRENT_UID = <?php echo (int)$uid; ?>;
const PAGE_URL = <?php echo json_encode(get_permalink()); ?>;
const FOLLOW_NONCE = '<?php echo esc_js(wp_create_nonce('post_interaction_nonce')); ?>';
// 編集データ（author.phpからの遷移時のみセット）
const EDIT_TL_DATA = <?php echo $edit_tl_data ? json_encode($edit_tl_data) : 'null'; ?>;

// フォロー状態キャッシュ（既存の following_users メタを使用）
<?php
$following_ids = array();
if ($uid) {
    $following_ids = get_user_meta($uid, 'following_users', true);
    if (!is_array($following_ids)) $following_ids = array();
}
?>
const tlFollowState = <?php echo json_encode(array_fill_keys(array_map('strval', $following_ids), true)); ?>;

/* ── カード一覧（全セクションから取得） ── */
function cards(){
    return Array.from(document.querySelectorAll('.tl-card'));
}

/* ================================================================
   トースト通知
   ================================================================ */
let toastTimer = null;
function showToast(msg){
    let t = document.getElementById('tl-toast');
    if(!t){
        t = document.createElement('div');
        t.id = 'tl-toast';
        t.className = 'tl-toast';
        document.body.appendChild(t);
    }
    t.innerHTML = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(()=> t.classList.remove('show'), 2200);
}

/* ================================================================
   共有
   ================================================================ */
function shortsShare(btn){
    const pid     = btn.dataset.pid;
    const vehicle = btn.dataset.vehicle || '鉄ログ';
    // ページURLに #post-{pid} を付与
    const shareUrl = PAGE_URL + (PAGE_URL.includes('?') ? '&' : '?') + 'tl=' + pid;
    const shareData = { title: vehicle + ' — 鉄ログ', url: shareUrl };

    if(navigator.share){
        navigator.share(shareData).catch(()=>{});
    } else {
        // Web Share API 未対応: クリップボードにコピー
        navigator.clipboard.writeText(shareUrl).then(()=>{
            showToast('<i class="fa-solid fa-link"></i> リンクをコピーしました');
        }).catch(()=>{
            // フォールバック
            const ta = document.createElement('textarea');
            ta.value = shareUrl;
            ta.style.cssText = 'position:fixed;top:-9999px;';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('<i class="fa-solid fa-link"></i> リンクをコピーしました');
        });
    }
}

/* ================================================================
   フォロー / フォロー解除（既存の user_follow アクションを使用）
   ================================================================ */
function shortsFollow(badge){
    if(!LOGGED){
        showToast('<i class="fa-solid fa-circle-info"></i> フォローするにはログインが必要です');
        return;
    }
    const authorId   = badge.dataset.authorId;
    const authorName = badge.dataset.author || '';
    if(!authorId || authorId == CURRENT_UID) return;
    if(badge._pending) return; // 二重送信防止
    badge._pending = true;

    const isFollowing = badge.classList.contains('following');
    const fd = new FormData();
    fd.append('action',    'user_follow');
    fd.append('nonce',     FOLLOW_NONCE);
    fd.append('author_id', authorId);

    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            badge._pending = false;
            if(!data.success) return;
            const nowFollowing = !isFollowing;
            tlFollowState[authorId] = nowFollowing;

            document.querySelectorAll('.tl-short-follow-badge').forEach(b => {
                if(b.dataset.authorId !== authorId) return;
                b.classList.toggle('following', nowFollowing);
                b.innerHTML = nowFollowing
                    ? '<i class="fa-solid fa-check" style="font-size:.5rem;"></i>'
                    : '<i class="fa-solid fa-plus" style="font-size:.6rem;"></i>';
                b.title = nowFollowing ? 'フォロー中' : 'フォローする';
            });
            showToast(nowFollowing
                ? `<i class="fa-solid fa-user-check"></i> ${authorName} をフォローしました`
                : `<i class="fa-solid fa-user-minus"></i> フォローを解除しました`
            );
        })
        .catch(() => { badge._pending = false; });
}

/* ================================================================
   検索 — クライアント側リアルタイムフィルター
   ================================================================ */
const searchInput = document.getElementById('tl-search-input');
const searchClear = document.getElementById('tl-search-clear');

function doSearch(q){
    q = q.trim().toLowerCase();
    const sections = document.querySelectorAll('.tl-section');
    let totalVisible = 0;

    sections.forEach(sec=>{
        let secVisible = 0;
        sec.querySelectorAll('.tl-card').forEach(card=>{
            const d = card.dataset;
            const text = [
                d.vehicle, d.series, d.author,
                d.category, d.tags
            ].join(' ').toLowerCase();
            const show = !q || text.includes(q);
            card.style.display = show ? '' : 'none';
            if(show) secVisible++;
        });
        // セクション丸ごと非表示
        sec.classList.toggle('hidden', secVisible === 0);
        totalVisible += secVisible;
    });

    // 全件 0 件表示
    let empty = document.getElementById('tl-search-empty');
    if(!empty){
        empty = document.createElement('div');
        empty.id = 'tl-search-empty';
        empty.className = 'tl-search-empty';
        empty.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i><div>「' + q + '」に一致する投稿が見つかりません</div>';
        document.querySelector('.tl-main').appendChild(empty);
    }
    empty.classList.toggle('show', totalVisible === 0 && q !== '');
    if(totalVisible === 0 && q !== ''){
        empty.querySelector('div').textContent = `「${q}」に一致する投稿が見つかりません`;
    }
}

if(searchInput){
    // ── 検索予測候補 ──
    const suggestEl = document.getElementById('tl-search-suggest');

    // 全カードからユニークな候補を収集
    function getSuggestions(q){
        if(!q || q.length < 1) return [];
        const ql = q.toLowerCase();
        const seen = new Set();
        const results = [];

        cards().forEach(c => {
            const d = c.dataset;
            const candidates = [
                {text: d.vehicle, type: 'vehicle', icon: 'fa-train'},
                {text: d.author,  type: 'author',  icon: 'fa-user'},
                ...(d.tags ? d.tags.split(/\s+/).filter(Boolean).map(t => ({text: t, type: 'tag', icon: 'fa-tag'})) : []),
                {text: d.category, type: 'category', icon: 'fa-folder'},
            ];
            candidates.forEach(({text, icon}) => {
                if(!text || seen.has(text) || !text.toLowerCase().includes(ql)) return;
                seen.add(text);
                results.push({text, icon});
            });
        });
        return results.slice(0, 8);
    }

    function highlight(text, q){
        const idx = text.toLowerCase().indexOf(q.toLowerCase());
        if(idx < 0) return text;
        return text.slice(0, idx) + '<mark>' + text.slice(idx, idx + q.length) + '</mark>' + text.slice(idx + q.length);
    }

    function renderSuggestions(q){
        if(!suggestEl) return;
        const items = getSuggestions(q);
        if(!items.length || !q){
            suggestEl.classList.remove('open');
            suggestEl.innerHTML = '';
            return;
        }
        suggestEl.innerHTML = items.map(({text, icon}) =>
            `<div class="tl-suggest-item" data-text="${text}">
                <i class="fa-solid ${icon}"></i>
                <span>${highlight(text, q)}</span>
            </div>`
        ).join('');
        suggestEl.classList.add('open');
        suggestEl.querySelectorAll('.tl-suggest-item').forEach(item => {
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                searchInput.value = item.dataset.text;
                searchClear.classList.add('show');
                doSearch(item.dataset.text);
                suggestEl.classList.remove('open');
                searchInput.blur();
            });
        });
    }

    searchInput.addEventListener('input', function(){
        const q = this.value;
        searchClear.classList.toggle('show', q.length > 0);
        doSearch(q);
        renderSuggestions(q);
    });
    searchInput.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){ this.value=''; searchClear.classList.remove('show'); doSearch(''); suggestEl.classList.remove('open'); this.blur(); }
        if(e.key === 'Enter'){ suggestEl.classList.remove('open'); }
    });
    searchInput.addEventListener('blur', () => {
        setTimeout(() => suggestEl.classList.remove('open'), 150);
    });
}
if(searchClear){
    searchClear.addEventListener('click', function(){
        searchInput.value = '';
        this.classList.remove('show');
        doSearch('');
        if(suggestEl) suggestEl.classList.remove('open');
        searchInput.focus();
    });
}

/* ================================================================
   PC：グリッドページネーション（2行固定）
   ================================================================ */
const tlNavState = {};

function tlGetCols(grid){
    // getComputedStyle でcolumn数を正確に取得
    const cols = getComputedStyle(grid).gridTemplateColumns.trim().split(/\s+/).length;
    return cols || 1;
}

function tlNavInit(type){
    if(window.innerWidth <= 600) return;
    const gridId = type === 'portrait' ? 'tl-grid-portrait' : 'tl-grid-' + type;
    const grid = document.getElementById(gridId);
    if(!grid) return;

    const cols    = tlGetCols(grid);
    const perPage = cols * 2; // 2行分
    const allCards = Array.from(grid.querySelectorAll('.tl-card'));

    if(allCards.length <= perPage){
        // 全件2行以内に収まる → ナビ不要
        const nav = document.getElementById('tl-nav-' + type);
        if(nav) nav.style.display = 'none';
        // display:none になっているカードがあれば全部見せる
        allCards.forEach(c => { c.style.display = ''; });
        return;
    }

    tlNavState[type] = { page: 0, perPage, total: allCards.length };
    tlNavRender(type);
}

function tlNavRender(type){
    const gridId = type === 'portrait' ? 'tl-grid-portrait' : 'tl-grid-' + type;
    const grid = document.getElementById(gridId);
    if(!grid) return;
    const s = tlNavState[type];
    if(!s) return;

    // colsを再取得（リサイズ対応）
    const cols = tlGetCols(grid);
    s.perPage  = cols * 2;

    const allCards  = Array.from(grid.querySelectorAll('.tl-card'));
    const totalPages = Math.ceil(s.total / s.perPage);
    s.page = Math.min(s.page, totalPages - 1);

    const start = s.page * s.perPage;
    const end   = start + s.perPage;

    allCards.forEach((c, i) => {
        const visible = (i >= start && i < end);
        c.style.display = visible ? '' : 'none';
        c.dataset.hidden = visible ? '' : '1';
    });

    const pageEl = document.getElementById('tl-nav-page-' + type);
    if(pageEl) pageEl.textContent = (s.page + 1) + ' / ' + totalPages;

    const prevBtn = document.getElementById('tl-nav-prev-' + type);
    const nextBtn = document.getElementById('tl-nav-next-' + type);
    if(prevBtn) prevBtn.disabled = (s.page === 0);
    if(nextBtn) nextBtn.disabled = (s.page >= totalPages - 1);
}

window.tlNavPage = function(type, dir){
    const s = tlNavState[type];
    if(!s) return;
    const totalPages = Math.ceil(s.total / s.perPage);
    s.page = Math.max(0, Math.min(totalPages - 1, s.page + dir));
    tlNavRender(type);
    const sec = document.getElementById('tl-section-' + type);
    if(sec) sec.scrollIntoView({behavior:'smooth', block:'start'});
};

// 初期化（DOMContentLoaded後・リサイズ時も再計算）
document.addEventListener('DOMContentLoaded', () => {
    ['portrait','image','landscape'].forEach(tlNavInit);
});
let tlNavResizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(tlNavResizeTimer);
    tlNavResizeTimer = setTimeout(() => {
        ['portrait','image','landscape'].forEach(tlNavInit);
    }, 200);
});

/* ================================================================
   スマホ：カルーセル表示制限 & すべて見るモーダル
   ================================================================ */
(function(){
    if(window.innerWidth > 600) return;
    const LIMIT = 5;
    const configs = [
        {gid:'tl-grid-portrait',  type:'portrait',  title:'縦動画 Shorts', cls:''},
        {gid:'tl-grid-image',     type:'image',     title:'画像',           cls:'landscape'},
        {gid:'tl-grid-landscape', type:'landscape', title:'横動画',         cls:'landscape'},
    ];
    configs.forEach(({gid, type, title, cls}) => {
        const grid = document.getElementById(gid);
        if(!grid) return;
        const allCards = Array.from(grid.querySelectorAll('.tl-card'));
        if(allCards.length <= LIMIT) return; // 5枚以下なら「すべて見る」不要

        // 6枚目以降を非表示
        allCards.forEach((c, i) => { if(i >= LIMIT) c.classList.add('tl-card-hidden'); });

        // 「すべて見る」カードを5枚目の直後に挿入
        const seeAll = document.createElement('div');
        seeAll.className = 'tl-see-all-card';
        seeAll.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
        seeAll.addEventListener('click', () => tlOpenAllModal(type, title));

        // 5枚目の次に挿入（5枚目 = index 4）
        const fifth = allCards[LIMIT - 1];
        if(fifth && fifth.nextSibling){
            grid.insertBefore(seeAll, fifth.nextSibling);
        } else {
            grid.appendChild(seeAll);
        }
    });
})();

window.tlOpenAllModal = function(type, title){
    const modal = document.getElementById('tl-all-modal');
    const body  = document.getElementById('tl-all-modal-body');
    document.getElementById('tl-all-modal-title').textContent = title;

    // 全件取得：表示・非表示問わずグリッドの全カード
    const gridId = type === 'portrait' ? 'tl-grid-portrait'
                 : type === 'image'    ? 'tl-grid-image'
                 : 'tl-grid-landscape';
    const grid = document.getElementById(gridId);
    body.innerHTML = '';

    if(grid){
        const allGrid = document.createElement('div');
        allGrid.className = 'tl-all-grid ' + type;

        // hidden含む全カードを取得（data-hidden='1'やtl-card-hiddenも含む）
        const cardEls = grid.querySelectorAll('.tl-card');
        document.getElementById('tl-all-modal-count').textContent = cardEls.length + '件';

        cardEls.forEach(card => {
            const clone = card.cloneNode(true);
            clone.classList.remove('tl-card-hidden');
            clone.style.display = '';
            clone.dataset.hidden = '';
            allGrid.appendChild(clone);
        });
        body.appendChild(allGrid);

        // クリックで再生
        allGrid.querySelectorAll('.tl-card').forEach(c => {
            c.addEventListener('click', () => {
                const orig = grid.querySelector('[data-pid="'+c.dataset.pid+'"]');
                tlOpenLb(orig || c);
                tlCloseAllModal();
            });
        });

        // サムネイル強制ロード：data-srcがあればsrcをセットして15%シーク
        allGrid.querySelectorAll('video').forEach(v => {
            if(!v.src && v.dataset.src) v.src = v.dataset.src;
            if(v.src) v.preload = 'metadata';
            function sk(){ if(v.duration>0) v.currentTime=Math.min(30,Math.max(0.5,v.duration*0.15)); }
            if(v.readyState>=1 && v.duration) sk();
            else if(v.src) v.addEventListener('loadedmetadata', sk, {once:true});
        });
    }

    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
};

window.tlCloseAllModal = function(){
    document.getElementById('tl-all-modal').classList.remove('open');
    document.body.style.overflow = '';
};

/* ── ページロード時に ?tl=ID があれば自動再生 ── */
(function(){
    const urlPid = new URLSearchParams(window.location.search).get('tl');
    if(!urlPid) return;

    function tryOpen(attempts){
        const card = document.querySelector('.tl-card[data-pid="' + urlPid + '"]');
        if(card){
            tlOpenLb(card);
        } else if(attempts > 0){
            // カードがまだレンダリングされていない場合はリトライ
            setTimeout(() => tryOpen(attempts - 1), 100);
        }
    }

    // DOMContentLoaded後に試行、最大10回リトライ
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', () => tryOpen(10));
    } else {
        tryOpen(10);
    }
})();

/* ================================================================
   ハンバーガーメニュー強制表示（スマホのみ）
   テーマがトグルするクラスを動的に検知して nav を表示
   ================================================================ */
(function(){
    if(window.innerWidth > 768) return;
    // ヘッダー内のボタンをすべて監視
    const headerBtns = document.querySelectorAll('header button, .site-header button, button.menu-toggle, button[aria-controls]');
    headerBtns.forEach(btn => {
        btn.addEventListener('click', function(){
            // クリック後に body や header に付与されるクラスを検知
            setTimeout(() => {
                const navEls = document.querySelectorAll(
                    'nav.site-navigation, .main-navigation, .primary-menu-container, nav[role="navigation"], .site-header nav, header nav'
                );
                navEls.forEach(nav => {
                    // 親要素のいずれかにopen/toggled系のクラスがあれば表示
                    const parent = nav.closest('.toggled, .nav-open, .menu-open, .is-open') || document.body.classList.contains('toggled') || document.body.classList.contains('nav-open');
                    if(parent || nav.classList.contains('toggled') || nav.classList.contains('is-open') || nav.getAttribute('aria-expanded') === 'true'){
                        nav.style.setProperty('display', 'block', 'important');
                    } else {
                        nav.style.removeProperty('display');
                    }
                });
            }, 50);
        });
    });
})();
let tlAddonCurrentTab = 'own';

// ── アドオンモーダル イベント登録 ──
const addonSelBtn    = document.getElementById('tl-addon-sel-btn');
const addonModalWrap = document.getElementById('tl-addon-modal-wrap');
const addonCloseBtn  = document.getElementById('tl-addon-modal-close');
const addonSearchExec= document.getElementById('tl-addon-search-exec');
const addonSearchInp = document.getElementById('tl-addon-search-input');
const addonTabOwn    = document.getElementById('tl-atab-own');
const addonTabSearch = document.getElementById('tl-atab-search');

function openAddonModal(){
    addonModalWrap.classList.add('open');
    tlAddonTab('own');
}
function closeAddonModal(){
    addonModalWrap.classList.remove('open');
}

if(addonSelBtn)    addonSelBtn.addEventListener('click', openAddonModal);
if(addonCloseBtn)  addonCloseBtn.addEventListener('click', closeAddonModal);
if(addonModalWrap) addonModalWrap.addEventListener('click', function(e){ if(e.target===this) closeAddonModal(); });
if(addonTabOwn)    addonTabOwn.addEventListener('click',    function(){ tlAddonTab('own'); });
if(addonTabSearch) addonTabSearch.addEventListener('click', function(){ tlAddonTab('search'); });
if(addonSearchExec)addonSearchExec.addEventListener('click', function(){ tlAddonSearch(); });
if(addonSearchInp) addonSearchInp.addEventListener('keydown', function(e){ if(e.key==='Enter') tlAddonSearch(); });

function tlAddonTab(tab){
    tlAddonCurrentTab = tab;
    if(addonTabOwn)    addonTabOwn.classList.toggle('on',    tab === 'own');
    if(addonTabSearch) addonTabSearch.classList.toggle('on', tab === 'search');
    const searchRow = document.getElementById('tl-addon-search-row');
    if(searchRow) searchRow.style.display = tab === 'search' ? 'flex' : 'none';
    if(tab === 'own') tlAddonLoadOwn();
    else {
        const list = document.getElementById('tl-addon-list');
        if(list) list.innerHTML = '<div class="tl-addon-empty">キーワードを入力して検索してください</div>';
    }
}

function tlAddonLoadOwn(){
    const list = document.getElementById('tl-addon-list');
    list.innerHTML = '<div class="tl-addon-loading"><div class="sp"></div></div>';
    const fd = new FormData();
    fd.append('action', 'tetsulog_get_my_posts');
    fd.append('nonce',  NONCE);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            if(data.success && data.data.posts.length > 0){
                tlAddonRenderList(data.data.posts);
            } else {
                list.innerHTML = '<div class="tl-addon-empty"><i class="fa-solid fa-inbox" style="display:block;font-size:2rem;margin-bottom:8px;"></i>投稿がまだありません</div>';
            }
        })
        .catch(() => { list.innerHTML = '<div class="tl-addon-empty">読み込みに失敗しました</div>'; });
}

function tlAddonSearch(){
    const q = document.getElementById('tl-addon-search-input').value.trim();
    if(!q) return;
    const list = document.getElementById('tl-addon-list');
    list.innerHTML = '<div class="tl-addon-loading"><div class="sp"></div></div>';
    const fd = new FormData();
    fd.append('action',  'tetsulog_search_posts');
    fd.append('nonce',   NONCE);
    fd.append('keyword', q);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            if(data.success && data.data.posts.length > 0){
                tlAddonRenderList(data.data.posts);
            } else {
                list.innerHTML = `<div class="tl-addon-empty">「${q}」に一致する投稿が見つかりませんでした</div>`;
            }
        })
        .catch(() => { list.innerHTML = '<div class="tl-addon-empty">検索に失敗しました</div>'; });
}

function tlAddonRenderList(posts){
    const list = document.getElementById('tl-addon-list');
    list.innerHTML = '';
    posts.forEach(post => {
        const item = document.createElement('div');
        item.className = 'tl-addon-item';
        item.innerHTML = `
            <img src="${post.thumb || ''}" onerror="this.style.display='none'" alt="">
            <div class="tl-addon-item-body">
                <div class="tl-addon-item-title">${post.title}</div>
                <div class="tl-addon-item-meta">${post.author} · ${post.date}</div>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:var(--yt-muted);font-size:.75rem;flex-shrink:0;"></i>`;
        item.addEventListener('click', () => tlAddonSelect(post.id, post.title));
        list.appendChild(item);
    });
}

function tlAddonSelect(id, title){
    document.getElementById('tl-addon-id-val').value = id;
    const btn = document.getElementById('tl-addon-sel-btn');
    const span = btn.querySelector('.selected-title');
    span.textContent = title;
    span.classList.remove('placeholder');
    span.style.color = '#fff';
    const clr = document.getElementById('tl-addon-clear');
    if(clr) clr.style.display = 'inline-block';
    closeAddonModal();
}

function tlAddonClear(){
    document.getElementById('tl-addon-id-val').value = '';
    const btn = document.getElementById('tl-addon-sel-btn');
    const span = btn.querySelector('.selected-title');
    span.textContent = '投稿を選択...';
    span.classList.add('placeholder');
    span.style.color = 'rgba(255,255,255,.4)';
    const clr = document.getElementById('tl-addon-clear');
    if(clr) clr.style.display = 'none';
}

// クリアボタンのイベント登録
const addonClearBtn = document.getElementById('tl-addon-clear');
if(addonClearBtn) addonClearBtn.addEventListener('click', tlAddonClear);

/* ================================================================
   SHORTS PLAYER — JavaScript
   ================================================================ */

let shortsCards  = [];   // グリッドのカード配列
let shortsIndex  = 0;    // 現在表示中のインデックス
let slideEls     = [];   // .tl-short-slide 要素配列

const shortsEl   = document.getElementById('tl-shorts');
const scrollEl   = document.getElementById('tl-shorts-scroll');
const arrUp      = document.getElementById('tl-arr-up');
const arrDown    = document.getElementById('tl-arr-down');

/* ── スライドを動的に生成 ── */
function buildSlides(startIndex){
    // 全カードを対象（ページネーションで非表示のカードも含む）
    // スマホのtl-card-hiddenのみ除外（全て見るモーダルを開いてからアクセスする場合は含む）
    shortsCards = cards();
    // 前回のオブザーバーを解除
    io.disconnect();
    currentPlayingSlide = null;
    scrollEl.innerHTML = '';
    slideEls = [];

    shortsCards.forEach((card, i) => {
        const d = card.dataset;
        const isVideo     = d.type === 'video';
        const isLandscape = d.orient === 'landscape' && isVideo;
        const isPortrait  = !isLandscape;
        const isLiked     = d.liked === '1';

        const slide = document.createElement('div');
        let slideClass = 'tl-short-slide';
        if(isVideo && isLandscape) slideClass += ' is-landscape';
        else if(isVideo)           slideClass += ' is-video';
        else                       slideClass += ' is-image';
        slide.className = slideClass;
        slide.dataset.index = i;
        slide.dataset.pid   = d.pid;

        /* ============================================================
           横動画：2カラムレイアウト（左:動画+カスタムコントロール / 右:情報）
           ============================================================ */
        if(isLandscape){
            // 左カラム
            const videoCol = document.createElement('div');
            videoCol.className = 'tl-ls-video-col';

            const mediaWrap = document.createElement('div');
            mediaWrap.className = 'tl-ls-media';

            const v = document.createElement('video');
            v.src = d.url || '';
            v.loop = true;
            v.muted = false;
            v.playsInline = true;
            v.preload = 'metadata';
            mediaWrap.appendChild(v);

            // 再生オーバーレイ（クリックで再生）
            const playOverlay = document.createElement('div');
            playOverlay.className = 'tl-ls-play-overlay';
            playOverlay.innerHTML = '<i class="fa-solid fa-circle-play"></i>';
            playOverlay.addEventListener('click', (e) => {
                e.stopPropagation();
                v.play().catch(()=>{});
                playOverlay.classList.add('hidden');
            });
            mediaWrap.appendChild(playOverlay);

            // メディアエリアタップで再生/停止
            mediaWrap.addEventListener('click', () => {
                if(v.paused){
                    v.play().catch(()=>{});
                    playOverlay.classList.add('hidden');
                } else {
                    v.pause();
                    playOverlay.classList.remove('hidden');
                }
            });

            videoCol.appendChild(mediaWrap);

            // ── カスタムコントロールバー ──
            const ctrl = document.createElement('div');
            ctrl.className = 'tl-ls-controls';

            // プログレスバー
            const progWrap = document.createElement('div');
            progWrap.className = 'tl-ls-progress-wrap';
            const progBar  = document.createElement('div');
            progBar.className = 'tl-ls-progress';
            const progThumb = document.createElement('div');
            progThumb.className = 'tl-ls-progress-thumb';
            const timeTooltip = document.createElement('div');
            timeTooltip.className = 'tl-ls-time-tooltip';
            progWrap.appendChild(progBar);
            progWrap.appendChild(progThumb);
            progWrap.appendChild(timeTooltip);

            function fmtTime(s){ const m=Math.floor(s/60); return m+':'+(String(Math.floor(s%60)).padStart(2,'0')); }

            // rAFでシークバーを毎フレーム滑らかに更新
            let rafId = null;
            function rafUpdate(){
                if(v.duration){
                    const pct = v.currentTime / v.duration * 100;
                    progBar.style.width = pct + '%';
                    progThumb.style.left = pct + '%';
                    timeEl.textContent = fmtTime(v.currentTime) + ' / ' + fmtTime(v.duration);
                }
                if(!v.paused && !v.ended) rafId = requestAnimationFrame(rafUpdate);
            }
            v.addEventListener('play',  () => { cancelAnimationFrame(rafId); rafId = requestAnimationFrame(rafUpdate); });
            v.addEventListener('pause', () => { cancelAnimationFrame(rafId); });
            v.addEventListener('ended', () => {
                cancelAnimationFrame(rafId);
                playOverlay.classList.remove('hidden');
            });
            v.addEventListener('seeked', () => {
                if(v.duration){
                    const pct = v.currentTime / v.duration * 100;
                    progBar.style.width = pct + '%';
                    progThumb.style.left = pct + '%';
                    timeEl.textContent = fmtTime(v.currentTime) + ' / ' + fmtTime(v.duration);
                }
            });
            v.addEventListener('loadedmetadata', () => {
                timeEl.textContent = '0:00 / ' + fmtTime(v.duration);
            });

            // シーク（クリック + ポインタードラッグ対応）
            let isSeeking = false;
            function seekFromEvent(e){
                const rect = progWrap.getBoundingClientRect();
                const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                if(v.duration) v.currentTime = pct * v.duration;
            }
            progWrap.addEventListener('pointerdown', e => {
                isSeeking = true;
                progWrap.setPointerCapture(e.pointerId);
                seekFromEvent(e);
            });
            progWrap.addEventListener('pointermove', e => {
                const rect = progWrap.getBoundingClientRect();
                const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                // ツールチップ更新
                timeTooltip.textContent = fmtTime(pct * (v.duration || 0));
                timeTooltip.style.left  = (pct * 100) + '%';
                // ドラッグ中はリアルタイムシーク
                if(isSeeking && v.duration) v.currentTime = pct * v.duration;
            });
            progWrap.addEventListener('pointerup',  () => { isSeeking = false; });
            progWrap.addEventListener('pointercancel', () => { isSeeking = false; });

            ctrl.appendChild(progWrap);

            // ボタン行
            const btnRow = document.createElement('div');
            btnRow.className = 'tl-ls-btn-row';

            // 再生/一時停止
            const playBtn = document.createElement('button');
            playBtn.className = 'tl-ls-btn';
            playBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
            playBtn.addEventListener('click', () => {
                if(v.paused){ v.play().catch(()=>{}); playOverlay.classList.add('hidden'); }
                else v.pause();
            });
            v.addEventListener('play',  () => { playBtn.innerHTML = '<i class="fa-solid fa-pause"></i>'; playOverlay.classList.add('hidden'); });
            v.addEventListener('pause', () => { playBtn.innerHTML = '<i class="fa-solid fa-play"></i>'; });

            // 巻き戻し
            const rewBtn = document.createElement('button');
            rewBtn.className = 'tl-ls-btn';
            rewBtn.innerHTML = '<i class="fa-solid fa-rotate-left"></i>';
            rewBtn.title = '10秒戻す';
            rewBtn.addEventListener('click', () => { v.currentTime = Math.max(0, v.currentTime - 10); });

            // 早送り
            const fwdBtn = document.createElement('button');
            fwdBtn.className = 'tl-ls-btn';
            fwdBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i>';
            fwdBtn.title = '10秒進む';
            fwdBtn.addEventListener('click', () => { v.currentTime = Math.min(v.duration || 0, v.currentTime + 10); });

            // 時間表示
            const timeEl = document.createElement('span');
            timeEl.className = 'tl-ls-time';
            timeEl.textContent = '0:00 / 0:00';

            const spacer = document.createElement('span');
            spacer.className = 'tl-ls-spacer';

            // 音量
            const volWrap = document.createElement('div');
            volWrap.className = 'tl-ls-vol-wrap';
            const muteBtn = document.createElement('button');
            muteBtn.className = 'tl-ls-btn';
            muteBtn.innerHTML = '<i class="fa-solid fa-volume-high"></i>';
            muteBtn.addEventListener('click', () => {
                v.muted = !v.muted;
                muteBtn.innerHTML = v.muted
                    ? '<i class="fa-solid fa-volume-xmark"></i>'
                    : '<i class="fa-solid fa-volume-high"></i>';
            });
            const volSlider = document.createElement('input');
            volSlider.type  = 'range';
            volSlider.min   = '0'; volSlider.max = '1'; volSlider.step = '0.05';
            volSlider.value = '0.8';
            volSlider.className = 'tl-ls-vol-slider';
            v.volume = 0.8;
            volSlider.addEventListener('input', () => { v.volume = volSlider.value; });
            volWrap.appendChild(muteBtn);
            volWrap.appendChild(volSlider);

            // フルスクリーン
            const fsBtn = document.createElement('button');
            fsBtn.className = 'tl-ls-btn';
            fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i>';
            fsBtn.addEventListener('click', () => {
                // iOS Safari はvideo要素のwebkitEnterFullscreenを使う
                if(v.webkitEnterFullscreen){
                    if(v.webkitDisplayingFullscreen){ v.webkitExitFullscreen(); }
                    else { v.webkitEnterFullscreen(); }
                } else if(!document.fullscreenElement){
                    (mediaWrap.requestFullscreen || mediaWrap.webkitRequestFullscreen).call(mediaWrap).catch(()=>{});
                } else {
                    (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                }
            });
            // フルスクリーン状態のアイコン更新
            const updateFsIcon = () => {
                const isFs = !!(document.fullscreenElement || v.webkitDisplayingFullscreen);
                fsBtn.innerHTML = isFs ? '<i class="fa-solid fa-compress"></i>' : '<i class="fa-solid fa-expand"></i>';
            };
            document.addEventListener('fullscreenchange', updateFsIcon);
            v.addEventListener('webkitbeginfullscreen', updateFsIcon);
            v.addEventListener('webkitendfullscreen', updateFsIcon);

            btnRow.appendChild(playBtn);
            btnRow.appendChild(rewBtn);
            btnRow.appendChild(fwdBtn);
            btnRow.appendChild(timeEl);
            btnRow.appendChild(spacer);
            btnRow.appendChild(volWrap);
            btnRow.appendChild(fsBtn);

            ctrl.appendChild(btnRow);
            videoCol.appendChild(ctrl);
            // ── 関連動画（動画カラムの真下） ──
            const relatedData = shortsCards.filter((c, ci) => {
                return ci !== i && c.dataset.orient === 'landscape' && c.dataset.type === 'video';
            }).slice(0, 5);

            if(relatedData.length > 0){
                const relSec = document.createElement('div');
                relSec.className = 'tl-ls-related';
                relSec.innerHTML = '<div class="tl-ls-related-title">関連動画</div>';
                const relList = document.createElement('div');
                relList.className = 'tl-ls-related-list';

                relatedData.forEach(rc => {
                    const rd = rc.dataset;
                    const ri = document.createElement('div');
                    ri.className = 'tl-ls-related-item';
                    ri.innerHTML = `
                        <div class="tl-ls-related-thumb">
                            ${rd.url ? `<video data-src="${rd.url}" muted preload="none" style="pointer-events:none;"></video>` : '<div style="width:100%;height:100%;background:#1a1a1a;display:flex;align-items:center;justify-content:center;"><i class=\'fa-solid fa-train\' style=\'color:#333;font-size:1rem;\'></i></div>'}
                            <span class="r-badge">VIDEO</span>
                        </div>
                        <div class="tl-ls-related-info">
                            <div class="tl-ls-related-vtitle">${rd.vehicle || '—'}</div>
                            <div class="tl-ls-related-meta">
                                <span>${rd.author || ''}</span>
                                <span>·</span>
                                <span><i class="fa-solid fa-heart" style="color:#ff4757;font-size:.6rem;"></i> ${rd.likes || 0}</span>
                                ${rd.views && parseInt(rd.views) > 0 ? `<span>·</span><span><i class="fa-regular fa-eye" style="font-size:.6rem;"></i> ${Number(rd.views).toLocaleString()}</span>` : ''}
                            </div>
                        </div>`;
                    ri.addEventListener('click', () => {
                        const targetIdx = shortsCards.indexOf(rc);
                        if(targetIdx >= 0){
                            shortsIndex = targetIdx;
                            scrollToIndex(targetIdx);
                        }
                    });
                    relList.appendChild(ri);
                });
                relSec.appendChild(relList);
                videoCol.appendChild(relSec);

                // 関連動画サムネイル：遅延ロード + 15%位置シーク
                relList.querySelectorAll('video[data-src]').forEach(rv => {
                    rv.src = rv.dataset.src;
                    rv.preload = 'metadata';
                    function seekRelThumb(){
                        if(rv.duration > 0)
                            rv.currentTime = Math.min(30, Math.max(0.5, rv.duration * 0.15));
                    }
                    if(rv.readyState >= 1 && rv.duration) seekRelThumb();
                    else rv.addEventListener('loadedmetadata', seekRelThumb, {once:true});
                });  // ← videoColの下に追加
            }

            slide.appendChild(videoCol);

            // 右カラム（情報パネル）
            const infoCol = document.createElement('div');
            infoCol.className = 'tl-ls-info-col';

            // タイトル
            const titleEl = document.createElement('div');
            titleEl.className = 'tl-ls-title';
            titleEl.textContent = d.vehicle || '—';
            infoCol.appendChild(titleEl);
            if(d.series){
                const seriesEl = document.createElement('div');
                seriesEl.className = 'tl-ls-series';
                seriesEl.textContent = d.series;
                infoCol.appendChild(seriesEl);
            }

            // 著者
            const authorRow2 = document.createElement('div');
            authorRow2.className = 'tl-ls-author-row';
            const avaImg2 = document.createElement('img');
            avaImg2.className = 'tl-ls-ava';
            avaImg2.src = d.avatar || '';
            avaImg2.alt = d.author || '';
            const aname2 = document.createElement('a');
            aname2.className = 'tl-ls-aname';
            aname2.href = d.authorUrl || '#';
            aname2.textContent = d.author || '';
            authorRow2.appendChild(avaImg2);
            authorRow2.appendChild(aname2);
            infoCol.appendChild(authorRow2);

            // アクションボタン群
            const actions = document.createElement('div');
            actions.className = 'tl-ls-actions';

            // いいね
            const lsLike = document.createElement('button');
            lsLike.className = 'tl-ls-action-btn' + (isLiked ? ' liked' : '');
            lsLike.dataset.pid   = d.pid;
            lsLike.dataset.liked = d.liked;
            lsLike.innerHTML = `<i class="fa-${isLiked?'solid':'regular'} fa-heart"></i> いいね <span class="ls-lc">${d.likes || 0}</span>`;
            lsLike.addEventListener('click', () => shortsLike(lsLike, i));
            actions.appendChild(lsLike);

            // 共有
            const lsShare = document.createElement('button');
            lsShare.className = 'tl-ls-action-btn';
            lsShare.dataset.pid     = d.pid;
            lsShare.dataset.vehicle = d.vehicle || '';
            lsShare.innerHTML = '<i class="fa-solid fa-share-nodes"></i> 共有';
            lsShare.addEventListener('click', () => shortsShare(lsShare));
            actions.appendChild(lsShare);

            // アドオン
            if(d.addonTitle && d.addonUrl){
                const lsAddon = document.createElement('a');
                lsAddon.className = 'tl-ls-action-btn addon';
                lsAddon.href = d.addonUrl;
                lsAddon.target = '_blank';
                lsAddon.rel = 'noopener';
                const addonImg = d.addonThumb
                    ? `<img src="${d.addonThumb}" style="width:20px;height:20px;border-radius:5px;object-fit:cover;flex-shrink:0;" alt="">`
                    : `<i class="fa-solid fa-cube"></i>`;
                lsAddon.innerHTML = `${addonImg} ${d.addonTitle}`;
                actions.appendChild(lsAddon);
            }

            infoCol.appendChild(actions);

            // 説明
            if(d.desc){
                const descEl2 = document.createElement('div');
                descEl2.className = 'tl-ls-desc';
                descEl2.textContent = d.desc;
                infoCol.appendChild(descEl2);
            }

            // タグ
            if(d.tags){
                const tagsEl2 = document.createElement('div');
                tagsEl2.className = 'tl-ls-tags';
                d.tags.split(/\s+/).filter(Boolean).forEach(t => {
                    const s = document.createElement('span');
                    s.className = 'tl-ls-tag';
                    s.textContent = '#' + t;
                    tagsEl2.appendChild(s);
                });
                infoCol.appendChild(tagsEl2);
            }

            // 日時
            const metaRow = document.createElement('div');
            metaRow.className = 'tl-ls-meta-row';
            metaRow.innerHTML = `<span><i class="fa-regular fa-clock"></i>${d.date||''}</span>`;
            if(d.views && parseInt(d.views) > 0){
                metaRow.innerHTML += `<span><i class="fa-regular fa-eye"></i>${Number(d.views).toLocaleString()} 回視聴</span>`;
            }
            infoCol.appendChild(metaRow);

            // 説明リンク化
            if(d.desc){
                const descEl2 = infoCol.querySelector('.tl-ls-desc');
                if(descEl2) autoLinkDesc(descEl2);
            }

            slide.appendChild(infoCol);
            scrollEl.appendChild(slide);
            slideEls.push(slide);
            return; // 横動画は以下のコードをスキップ
        }

        /* ============================================================
           縦動画・画像：既存Shortsスタイル
           ============================================================ */

        /* 画像背景ブラー */
        if(!isVideo && d.url){
            const bg = document.createElement('div');
            bg.className = 'tl-short-bg';
            bg.style.backgroundImage = `url(${d.url})`;
            slide.appendChild(bg);
        }

        /* メディア */
        const mediaWrap = document.createElement('div');
        mediaWrap.className = 'tl-short-media';

        if(d.url){
            if(isVideo){
                const v = document.createElement('video');
                v.src = d.url;
                v.loop = true;
                v.muted = false;
                v.playsInline = true;
                v.preload = 'metadata';
                v.setAttribute('playsinline','');
                mediaWrap.appendChild(v);
            } else {
                const img = document.createElement('img');
                img.src = d.url;
                img.alt = d.vehicle || '';
                img.loading = 'lazy';
                mediaWrap.appendChild(img);
            }
        } else {
            mediaWrap.innerHTML = '<div style="width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-train" style="font-size:4rem;color:#333;"></i></div>';
        }
        slide.appendChild(mediaWrap);

        /* タップオーバーレイ（縦動画のみ） */
        if(isVideo){
            const tap = document.createElement('div');
            tap.className = 'tl-short-tap';
            tap.addEventListener('click', ()=> togglePlay(slide));
            slide.appendChild(tap);

            const playIc = document.createElement('div');
            playIc.className = 'tl-short-play-ic';
            playIc.innerHTML = '<i class="fa-solid fa-pause"></i>';
            slide.appendChild(playIc);

            const ld = document.createElement('div');
            ld.className = 'tl-short-loading';
            ld.innerHTML = '<div class="tl-short-loading-sp"></div>';
            slide.appendChild(ld);
        }

        /* 右サイドボタン */
        const side = document.createElement('div');
        side.className = 'tl-short-side';

        const likeBtn = document.createElement('button');
        likeBtn.className = 'tl-short-side-btn' + (isLiked ? ' liked' : '');
        likeBtn.dataset.pid   = d.pid;
        likeBtn.dataset.liked = d.liked;
        likeBtn.innerHTML = `
            <div class="ic"><i class="fa-${isLiked?'solid':'regular'} fa-heart"></i></div>
            <span class="slc">${d.likes || 0}</span>`;
        likeBtn.addEventListener('click', ()=> shortsLike(likeBtn, i));
        side.appendChild(likeBtn);

        const shareBtn = document.createElement('button');
        shareBtn.className = 'tl-short-side-btn tl-short-share-btn';
        shareBtn.dataset.pid     = d.pid;
        shareBtn.dataset.vehicle = d.vehicle || '';
        shareBtn.innerHTML = `<div class="ic"><i class="fa-solid fa-share-nodes"></i></div><span>共有</span>`;
        shareBtn.addEventListener('click', ()=> shortsShare(shareBtn));
        side.appendChild(shareBtn);

        if(d.addonTitle && d.addonUrl){
            const addonBtn = document.createElement('a');
            addonBtn.className = 'tl-short-side-btn tl-short-addon-btn';
            addonBtn.href   = d.addonUrl;
            addonBtn.target = '_blank';
            addonBtn.rel    = 'noopener';
            const addonIcHTML = d.addonThumb
                ? `<div class="ic" style="overflow:hidden;border-radius:8px;border:none;padding:0;"><img src="${d.addonThumb}" style="width:100%;height:100%;object-fit:cover;display:block;" alt=""></div>`
                : `<div class="ic"><i class="fa-solid fa-cube"></i></div>`;
            addonBtn.innerHTML = `${addonIcHTML}<span>アドオン</span>`;
            side.appendChild(addonBtn);
        }

        slide.appendChild(side);

        /* 下部情報 */
        const info = document.createElement('div');
        info.className = 'tl-short-info';

        const authorRow = document.createElement('div');
        authorRow.className = 'tl-short-author';

        const avaWrap = document.createElement('div');
        avaWrap.className = 'tl-short-ava-wrap';

        const avaLink = document.createElement('a');
        avaLink.className = 'tl-short-ava-link';
        avaLink.href = d.authorUrl || '#';
        avaLink.addEventListener('click', e => e.stopPropagation());
        const avaImg = document.createElement('img');
        avaImg.className = 'tl-short-ava';
        avaImg.src = d.avatar || '';
        avaImg.alt = d.author || '';
        avaLink.appendChild(avaImg);
        avaWrap.appendChild(avaLink);

        const authorId    = d.authorId || '';
        const isFollowing = tlFollowState[authorId] || false;
        const isSelf      = authorId && authorId == CURRENT_UID;
        const badge = document.createElement('button');
        badge.className = 'tl-short-follow-badge'
            + (isFollowing ? ' following' : '')
            + (isSelf ? ' hidden' : '');
        badge.dataset.authorId = authorId;
        badge.dataset.author   = d.author || '';
        badge.innerHTML = isFollowing
            ? '<i class="fa-solid fa-check" style="font-size:.5rem;"></i>'
            : '<i class="fa-solid fa-plus" style="font-size:.6rem;"></i>';
        badge.title = isFollowing ? 'フォロー中' : 'フォローする';
        badge.addEventListener('click', e => { e.stopPropagation(); shortsFollow(badge); });
        avaWrap.appendChild(badge);

        authorRow.appendChild(avaWrap);

        const anameLink = document.createElement('a');
        anameLink.className = 'tl-short-aname';
        anameLink.href = d.authorUrl || '#';
        anameLink.textContent = d.author || '';
        anameLink.addEventListener('click', e => e.stopPropagation());
        authorRow.appendChild(anameLink);

        info.appendChild(authorRow);

        const vehicleEl = document.createElement('div');
        vehicleEl.className = 'tl-short-vehicle';
        vehicleEl.textContent = d.vehicle || '—';
        info.appendChild(vehicleEl);

        if(d.series){
            const subEl = document.createElement('div');
            subEl.className = 'tl-short-sub';
            subEl.textContent = d.series;
            info.appendChild(subEl);
        }

        if(d.tags){
            const tagsEl = document.createElement('div');
            tagsEl.className = 'tl-short-tags';
            d.tags.split(/\s+/).filter(Boolean).forEach(t=>{
                const s = document.createElement('span');
                s.className = 'tl-short-tag';
                s.textContent = '#'+t;
                tagsEl.appendChild(s);
            });
            info.appendChild(tagsEl);
        }

        if(d.desc){
            const descEl = document.createElement('div');
            descEl.className = 'tl-short-desc' + (isPortrait ? ' collapsed' : '');
            descEl.textContent = d.desc;
            info.appendChild(descEl);
            // URL自動リンク化
            autoLinkDesc(descEl);

            if(isPortrait){
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'tl-short-desc-toggle';
                toggleBtn.textContent = 'もっと見る';
                toggleBtn.addEventListener('click', e => {
                    e.stopPropagation();
                    const collapsed = descEl.classList.toggle('collapsed');
                    toggleBtn.textContent = collapsed ? 'もっと見る' : '閉じる';
                    // 親スライドのグラデーション制御
                    const parentSlide = slide;
                    if(collapsed){
                        parentSlide.classList.remove('desc-expanded');
                    } else {
                        parentSlide.classList.add('desc-expanded');
                    }
                });
                info.appendChild(toggleBtn);
            }
        }

        // 視聴回数（縦動画・画像）
        if(d.views && parseInt(d.views) > 0){
            const viewEl = document.createElement('div');
            viewEl.style.cssText = 'font-size:.7rem;color:rgba(255,255,255,.4);margin-top:4px;display:flex;align-items:center;gap:3px;';
            viewEl.innerHTML = `<i class="fa-regular fa-eye"></i>${Number(d.views).toLocaleString()} 回視聴`;
            info.appendChild(viewEl);
        }

        slide.appendChild(info);
        scrollEl.appendChild(slide);
        slideEls.push(slide);
    });
}

/* ── Shortsを開く ── */
window.tlOpenLb = function(card){
    if(!card) return;
    buildSlides();

    const pid = card.dataset.pid;
    shortsIndex = 0;
    if(pid){
        const found = slideEls.findIndex(s => s.dataset.pid === pid);
        if(found >= 0) shortsIndex = found;
    } else {
        const visibleCards = cards().filter(c => !c.classList.contains('tl-card-hidden') && c.style.display !== 'none');
        const vi = visibleCards.indexOf(card);
        if(vi >= 0) shortsIndex = vi;
    }

    shortsEl.classList.add('open');
    document.body.style.overflow = 'hidden';
    document.body.style.touchAction = 'none';
    document.documentElement.style.overflow = 'hidden';

    // URLをこの動画のIDに更新
    const openPid = slideEls[shortsIndex]?.dataset.pid || pid;
    if(openPid) tlUpdateUrl(openPid);

    requestAnimationFrame(() => {
        for(let i = Math.max(0, shortsIndex-1); i <= Math.min(slideEls.length-1, shortsIndex+2); i++){
            const v = slideEls[i]?.querySelector('video');
            if(v && !v.src && v.dataset?.src){
                v.src = v.dataset.src;
                v.preload = 'metadata';
            }
        }
    });
    updateArrows();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            const target = slideEls[shortsIndex];
            if(target) scrollEl.scrollTo({top: target.offsetTop, behavior:'instant'});
        });
    });
};

/* ── Shortsを閉じる ── */
/* ── URL ↔ 動画ID の同期 ── */
function tlUpdateUrl(pid){
    const base = PAGE_URL.split('?')[0];
    const params = new URLSearchParams(window.location.search);
    // フィルター系パラメータは維持、tlだけ上書き
    params.set('tl', pid);
    history.replaceState({tl: pid}, '', base + '?' + params.toString());
}
function tlClearUrl(){
    const params = new URLSearchParams(window.location.search);
    params.delete('tl');
    const qs = params.toString();
    history.replaceState({}, '', PAGE_URL.split('?')[0] + (qs ? '?' + qs : ''));
}

function closeShorts(){
    scrollEl.querySelectorAll('video').forEach(v=>{ v.pause(); v.currentTime=0; });
    shortsEl.classList.remove('open');
    document.body.style.overflow = '';
    document.body.style.touchAction = '';
    document.documentElement.style.overflow = '';
    currentPlayingSlide = null;
    tlClearUrl(); // URLをホームに戻す
}
window.closeShorts = closeShorts;
document.getElementById('tl-shorts-close').addEventListener('click', closeShorts);

// 戻るボタン
const backBtn  = document.getElementById('tl-short-back-btn');
const backZone = document.getElementById('tl-short-back-zone');

function updateBackBtnPosition(){
    // ヘッダーの高さを取得してボタンをその下に配置
    const header = document.querySelector('#masthead, .site-header, header');
    const headerH = header ? header.offsetHeight : 0;
    if(backZone) backZone.style.top = headerH + 'px';
}

function showBackBtn(){ if(backBtn) backBtn.classList.add('visible'); }
function hideBackBtn(){ if(backBtn) backBtn.classList.remove('visible'); }

if(backBtn){
    backBtn.addEventListener('click', closeShorts);
    backZone.addEventListener('touchstart', () => {
        backBtn.classList.add('visible');
    }, {passive:true});
    backZone.addEventListener('touchend', (e) => {
        e.preventDefault();
        closeShorts();
    });
}

// Shortsが開いたときにヘッダー高さを再計算
const _origOpen = window.tlOpenLb;
window.tlOpenLb = function(card){
    _origOpen(card);
    updateBackBtnPosition();
    // 最初は非表示（再生開始後に動画停止で表示）
    hideBackBtn();
};

window.addEventListener('resize', updateBackBtnPosition);
document.addEventListener('keydown', e=>{
    if(!shortsEl.classList.contains('open')) return;
    if(e.key === 'Escape')    closeShorts();
    if(e.key === 'ArrowUp')   scrollToPrev();
    if(e.key === 'ArrowDown') scrollToNext();
});

/* ── スクロールナビ ── */
function scrollToPrev(){
    if(shortsIndex <= 0) return;
    shortsIndex--;
    scrollToIndex(shortsIndex);
}
function scrollToNext(){
    if(shortsIndex >= slideEls.length-1) return;
    shortsIndex++;
    scrollToIndex(shortsIndex);
}
function scrollToIndex(idx){
    const target = slideEls[idx];
    if(!target) return;
    scrollEl.scrollTo({top: target.offsetTop, behavior:'smooth'});
    // 次のスライドの動画も先読み
    requestAnimationFrame(() => {
        [idx+1, idx+2].forEach(ni => {
            const v = slideEls[ni]?.querySelector('video');
            if(v && !v.src && v.dataset?.src){
                v.src = v.dataset.src;
                v.preload = 'metadata';
            }
        });
    });
}
arrUp  .addEventListener('click', scrollToPrev);
arrDown.addEventListener('click', scrollToNext);

function updateArrows(){
    arrUp  .disabled = (shortsIndex <= 0);
    arrDown.disabled = (shortsIndex >= slideEls.length-1);
}

/* ── IntersectionObserver でスナップ検知 ── */
let currentPlayingSlide = null; // 現在再生中のスライドを追跡

const io = new IntersectionObserver(entries=>{
    entries.forEach(entry=>{
        if(!entry.isIntersecting) return;
        const slide = entry.target;
        const idx = parseInt(slide.dataset.index);
        shortsIndex = idx;
        updateArrows();

        // URLをこの動画IDに更新（スワイプ時）
        if(slide.dataset.pid) tlUpdateUrl(slide.dataset.pid);

        // 既に同じスライドが再生中なら何もしない
        if(currentPlayingSlide === slide) return;

        // 視聴回数カウントアップ
        const pid = slide.dataset.pid;
        if(pid){
            const fd = new FormData();
            fd.append('action',  'tetsulog_increment_view');
            fd.append('post_id', pid);
            fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).catch(()=>{});
        }

        // 他スライドの動画を先に停止
        slideEls.forEach((s, i)=>{
            if(i !== idx){
                const ov = s.querySelector('video');
                if(ov && !ov.paused){ ov.pause(); ov.currentTime=0; }
                s.classList.remove('playing');
            }
        });

        currentPlayingSlide = slide;

        // このスライドの動画を再生
        const v = slide.querySelector('video');
        if(v){
            if(slide.classList.contains('is-landscape')){
                // 横動画：イベントリスナーは一度だけ付ける
                if(!slide._listenersAttached){
                    slide._listenersAttached = true;
                    v.addEventListener('pause', showBackBtn);
                    v.addEventListener('play',  hideBackBtn);
                }
                if(v.paused){
                    v.play().catch(()=>{});
                    const ov = slide.querySelector('.tl-ls-play-overlay');
                    if(ov) ov.classList.add('hidden');
                }
                hideBackBtn();
            } else if(!slide.classList.contains('is-image')){
                // 縦動画
                if(v.paused){
                    v.play().catch(()=>{});
                    slide.classList.add('playing');
                    updatePlayIcon(slide, false);
                }
                hideBackBtn();
            }
        }

        // 画像は戻るボタン常時表示
        if(slide.classList.contains('is-image')) showBackBtn();
    });
},{threshold:0.6});

/* ── 説明欄のURL自動リンク化 ── */
function autoLinkDesc(el){
    if(!el) return;
    const text = el.textContent || '';
    const linked = text.replace(
        /(https?:\/\/[^\s\u3000　、。！？「」【】]+)/g,
        '<a href="$1" target="_blank" rel="noopener" style="color:var(--yt-red);text-decoration:underline;word-break:break-all;">$1</a>'
    );
    if(linked !== text) el.innerHTML = linked;
}

// スライド生成後にオブザーブ
function observeSlides(){
    slideEls.forEach(s=> io.observe(s));
}

// buildSlidesの後に observeSlides を呼ぶよう修正
const _buildSlides = buildSlides;
buildSlides = function(startIndex){
    _buildSlides(startIndex);
    observeSlides();
};

/* ── 動画 再生/一時停止 ── */
function togglePlay(slide){
    const v = slide.querySelector('video');
    if(!v) return;
    if(v.paused){
        v.play().catch(()=>{});
        slide.classList.add('playing');
        updatePlayIcon(slide, false);
        hideBackBtn();
    } else {
        v.pause();
        slide.classList.remove('playing');
        updatePlayIcon(slide, true);
        showBackBtn();
    }
}
function updatePlayIcon(slide, showPlay){
    const ic = slide.querySelector('.tl-short-play-ic');
    if(!ic) return;
    ic.innerHTML = showPlay
        ? '<i class="fa-solid fa-play"></i>'
        : '<i class="fa-solid fa-pause"></i>';
    ic.classList.add('show');
    clearTimeout(ic._timer);
    ic._timer = setTimeout(()=> ic.classList.remove('show'), 800);
}

/* ── Shortsいいね ── */
function shortsLike(btn, slideIdx){
    if(!LOGGED){
        showToast('<i class="fa-solid fa-circle-info"></i> いいねするにはログインが必要です');
        return;
    }
    const pid     = btn.dataset.pid;
    const isLiked = btn.classList.contains('liked');
    doLike(pid, isLiked, function(liked, count){
        btn.dataset.liked = liked ? '1' : '0';
        btn.classList.toggle('liked', liked);
        // 縦動画サイドボタン
        const icoEl = btn.querySelector('i');
        if(icoEl) icoEl.className = liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        // カウント（slc or ls-lc）
        const cntEl = btn.querySelector('.slc, .ls-lc');
        if(cntEl) cntEl.textContent = count;
        const card = shortsCards[slideIdx];
        if(card) syncCardLike(card, liked, count);
    });
}

/* ================================================================
   いいね（グリッド）
   ================================================================ */
window.tlLike = function(btn){
    if(!LOGGED){
        showToast('<i class="fa-solid fa-circle-info"></i> いいねするにはログインが必要です');
        return;
    }
    const pid     = btn.dataset.pid;
    const isLiked = btn.classList.contains('liked');
    doLike(pid, isLiked, function(liked, count){
        btn.dataset.liked = liked ? '1' : '0';
        syncCardLike(btn.closest('.tl-card'), liked, count);
    });
};

function doLike(pid, currentlyLiked, cb){
    const fd = new FormData();
    fd.append('action',  'tetsulog_like');
    fd.append('nonce',   NONCE);
    fd.append('post_id', pid);
    if(currentlyLiked) fd.append('unlike', '1');
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'})
        .then(r => r.json())
        .then(d => {
            if(d.success){
                // サーバーが返す liked 状態を優先、なければトグル
                const newLiked = (d.data && d.data.liked !== undefined)
                    ? d.data.liked
                    : !currentlyLiked;
                const count = (d.data && d.data.count !== undefined)
                    ? d.data.count
                    : 0;
                cb(newLiked, count);
            }
        })
        .catch(console.error);
}

function syncCardLike(card, liked, count){
    if(!card) return;
    card.dataset.liked = liked ? '1' : '0';
    card.dataset.likes = count;
    const btn  = card.querySelector('.tl-like-btn');
    if(!btn) return;
    btn.querySelector('i').className = liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
    btn.classList.toggle('liked', liked);
    btn.querySelector('.lc').textContent = count;
}

/* ================================================================
   削除
   ================================================================ */
window.tlDelete = function(btn){
    if(!confirm('この鉄ログを削除しますか？')) return;
    const pid   = btn.dataset.pid;
    const nonce = btn.dataset.nonce;
    const fd = new FormData();
    fd.append('action',  'delete_tetsulog_post');
    fd.append('nonce',   nonce);
    fd.append('post_id', pid);
    fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'})
        .then(r=>r.json())
        .then(d=>{
            if(d.success){
                const card = btn.closest('.tl-card');
                card.style.transition = 'opacity .25s,transform .25s';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(.9)';
                setTimeout(()=>card.remove(), 260);
            } else {
                alert(d.data || '削除に失敗しました');
            }
        });
};

/* ================================================================
   グリッドカード動画サムネイル：IntersectionObserver で遅延ロード + 15%シーク
   ================================================================ */
(function(){
    function seekThumb(v){
        if(v.duration > 0)
            v.currentTime = Math.min(30, Math.max(0.5, v.duration * 0.15));
    }
    function loadVideo(v){
        if(v.src) return; // 既にロード済み
        v.src = v.dataset.src;
        v.preload = 'metadata';
        if(v.readyState >= 1 && v.duration) seekThumb(v);
        else v.addEventListener('loadedmetadata', ()=> seekThumb(v), {once:true});
    }

    const thumbObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if(!entry.isIntersecting) return;
            const v = entry.target;
            loadVideo(v);
            thumbObserver.unobserve(v);
        });
    }, {rootMargin:'200px'}); // 200px手前から先読み

    document.querySelectorAll('.tl-card[data-type="video"] video[data-src]').forEach(v => {
        thumbObserver.observe(v);
    });

    // ホバー再生（タッチデバイス除く）
    const isTouchDevice = window.matchMedia('(pointer: coarse)').matches;
    document.querySelectorAll('.tl-card[data-type="video"]').forEach(card => {
        const v = card.querySelector('video');
        if(!v) return;
        if(!isTouchDevice){
            card.addEventListener('mouseenter', ()=>{
                loadVideo(v); // まだロードされていなければ即ロード
                v.currentTime = 0;
                v.play().catch(()=>{});
            });
            card.addEventListener('mouseleave', ()=>{
                v.pause();
                seekThumb(v);
            });
        }
    });
})();

/* ================================================================
   モーダル
   ================================================================ */
const modal      = document.getElementById('tl-modal');
const openBtn    = document.getElementById('tl-open-modal');
const closeBtn   = document.getElementById('tl-modal-close');

if(openBtn) openBtn.addEventListener('click', ()=> modal.classList.add('open'));
if(closeBtn) closeBtn.addEventListener('click', ()=> { modal.classList.remove('open'); tlEditReset(); });
modal.addEventListener('click', e=>{ if(e.target===modal){ modal.classList.remove('open'); tlEditReset(); } });

/* ── 編集モード：ページロード時に自動で入力 ── */
function tlEditFill(data){
    if(!data) return;
    modal.classList.add('open');

    // 編集中フラグ
    document.getElementById('tl-edit-post-id').value = data.id;

    // タイトル変更
    const mtitle = modal.querySelector('.tl-mtitle');
    if(mtitle) mtitle.innerHTML = '<i class="fa-solid fa-pen"></i> 鉄ログを編集';
    const submitBtn2 = document.getElementById('tl-submit');
    if(submitBtn2) submitBtn2.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> 更新する';

    // タイプ切り替え
    const t = data.type || 'image';
    document.querySelectorAll('.tl-topt').forEach(b => b.classList.toggle('on', b.dataset.type === t));
    document.getElementById('tl-sel-type').value = t;

    // フォーム入力
    const form = document.getElementById('tl-form');
    if(!form) return;
    const vf = form.querySelector('[name="vehicle_name"]');
    const cf = form.querySelector('[name="tetsulog_category"]');
    const tf = form.querySelector('[name="tetsulog_tags"]');
    if(vf) vf.value = data.vehicle  || '';
    if(tf) tf.value = data.tags     || '';
    if(cf) cf.value = data.category || '';
    const df = form.querySelector('[name="tetsulog_description"]');
    if(df) df.value = data.desc || '';

    // アドオン
    if(data.addon_id){
        document.getElementById('tl-addon-id-val').value = data.addon_id;
        const selBtn = document.getElementById('tl-addon-sel-btn');
        if(selBtn){
            const span = selBtn.querySelector('.selected-title');
            span.textContent = 'ID: ' + data.addon_id;
            span.classList.remove('placeholder');
            span.style.color = '#fff';
            const clr = document.getElementById('tl-addon-clear');
            if(clr) clr.style.display = 'inline-block';
        }
    }

    // 現在のメディアプレビュー表示
    if(data.media_url){
        const prevW = document.getElementById('tl-prev-wrap');
        const prevImg = document.getElementById('tl-prev-img');
        const prevVid = document.getElementById('tl-prev-vid');
        if(prevW) prevW.style.display = 'block';
        if(t === 'video'){
            if(prevVid){ prevVid.src = data.media_url; prevVid.style.display = 'block'; }
            if(prevImg) prevImg.style.display = 'none';
        } else {
            if(prevImg){ prevImg.src = data.media_url; prevImg.style.display = 'block'; }
            if(prevVid) prevVid.style.display = 'none';
        }
        // ファイルなし = 既存メディアを維持する旨を表示
        const hint = document.getElementById('tl-file-hint');
        if(hint){ hint.textContent = '変更しない場合はファイル選択不要'; hint.style.color = '#888'; }
    }
}

function tlEditReset(){
    document.getElementById('tl-edit-post-id').value = '';
    const mtitle = modal.querySelector('.tl-mtitle');
    if(mtitle) mtitle.innerHTML = '<i class="fa-solid fa-video"></i> 鉄ログを投稿';
    const submitBtn2 = document.getElementById('tl-submit');
    if(submitBtn2) submitBtn2.innerHTML = '<i class="fa-solid fa-paper-plane"></i> 投稿する';
}

// ページロード時に編集データがあれば自動で開く
if(EDIT_TL_DATA && LOGGED){
    document.addEventListener('DOMContentLoaded', ()=> { tlEditFill(EDIT_TL_DATA); });
}

/* タイプ切替 */
document.querySelectorAll('.tl-topt').forEach(btn=>{
    btn.addEventListener('click', function(){
        document.querySelectorAll('.tl-topt').forEach(b=>b.classList.remove('on'));
        this.classList.add('on');
        const t = this.dataset.type;
        document.getElementById('tl-sel-type').value = t;
        const fi = document.getElementById('tl-file');
        const hint = document.getElementById('tl-file-hint');
        if(t === 'video'){
            fi.accept = 'video/mp4';
            hint.textContent = 'MP4 • 最大50MB • 30秒以内';
        } else {
            fi.accept = 'image/*';
            hint.textContent = 'JPG / PNG / WebP / GIF • 最大10MB';
        }
        clearPreview();
    });
});

/* ドロップゾーン */
const drop   = document.getElementById('tl-drop');
const fileIn = document.getElementById('tl-file');
const prevW  = document.getElementById('tl-prev-wrap');
const prevImg= document.getElementById('tl-prev-img');
const prevVid= document.getElementById('tl-prev-vid');
let selFile        = null;
let selOrientation = 'landscape';

/* ── 向き検出（専用の非表示video/imgで確実に検出） ── */
function detectOrientation(file, type){
    return new Promise(function(resolve){
        const url = URL.createObjectURL(file);

        if(type === 'video'){
            // 画面外に置いた専用video要素で検出
            const v = document.createElement('video');
            v.muted      = true;
            v.preload    = 'metadata';
            v.playsInline= true;
            v.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;';
            document.body.appendChild(v);

            let resolved = false;
            function done(){
                if(resolved) return;
                resolved = true;
                const w = v.videoWidth;
                const h = v.videoHeight;
                document.body.removeChild(v);
                URL.revokeObjectURL(url);
                resolve(h > 0 && h > w ? 'portrait' : 'landscape');
            }

            v.addEventListener('loadedmetadata', done);
            v.addEventListener('error', function(){ done(); }); // エラー時はlandscapeで続行
            setTimeout(done, 5000); // 5秒タイムアウト
            v.src = url;
            v.load();

        } else {
            const img = new Image();
            img.onload = function(){
                URL.revokeObjectURL(url);
                resolve(img.naturalHeight > img.naturalWidth ? 'portrait' : 'landscape');
            };
            img.onerror = function(){ URL.revokeObjectURL(url); resolve('landscape'); };
            img.src = url;
        }
    });
}

function clearPreview(){
    selFile        = null;
    selOrientation = 'landscape';
    prevW.style.display = 'none';
    prevImg.src = '';
    prevVid.src = '';
    const hint = document.getElementById('tl-file-hint');
    if(hint){ hint.textContent = ''; hint.style.color = ''; }
    if(fileIn) fileIn.value = '';
}

async function showPreview(file){
    selFile = file;
    const t   = document.getElementById('tl-sel-type').value;
    const url = URL.createObjectURL(file);
    prevW.style.display = 'block';

    if(t === 'video'){
        prevVid.src = url;
        prevVid.style.display = 'block';
        prevImg.style.display = 'none';
    } else {
        prevImg.src = url;
        prevImg.style.display = 'block';
        prevVid.style.display = 'none';
    }

    // 向き検出（専用要素で確実に）
    const hint = document.getElementById('tl-file-hint');
    if(hint){ hint.textContent = '検出中...'; hint.style.color = '#888'; }

    selOrientation = await detectOrientation(file, t);

    if(hint){
        hint.textContent = selOrientation === 'portrait' ? '✓ 縦動画として登録されます' : '✓ 横動画として登録されます';
        hint.style.color = selOrientation === 'portrait' ? 'var(--yt-red)' : '#888';
    }
}

if(fileIn) fileIn.addEventListener('change', function(){ if(this.files[0]) showPreview(this.files[0]); });
if(drop){
    drop.addEventListener('dragover', e=>{ e.preventDefault(); drop.classList.add('dz'); });
    drop.addEventListener('dragleave', ()=> drop.classList.remove('dz'));
    drop.addEventListener('drop', e=>{
        e.preventDefault(); drop.classList.remove('dz');
        const f = e.dataTransfer.files[0];
        if(f) showPreview(f);
    });
}

/* アラート表示 */
function showAlert(type, msg){
    const el = document.getElementById('tl-alert');
    el.className = 'tl-alert ' + type;
    el.textContent = msg;
    el.style.display = 'block';
    el.scrollIntoView({behavior:'smooth',block:'nearest'});
}

/* 投稿送信 */
const submitBtn = document.getElementById('tl-submit');
if(submitBtn) submitBtn.addEventListener('click', async function(){
    const alertEl = document.getElementById('tl-alert');
    alertEl.style.display = 'none';

    const editId = document.getElementById('tl-edit-post-id').value;
    const isEdit = !!editId;

    if(!isEdit && !selFile){ showAlert('err','ファイルを選択してください。'); return; }

    const form = document.getElementById('tl-form');
    const vname = form.querySelector('[name="vehicle_name"]').value.trim();
    if(!vname){ showAlert('err','車両名を入力してください。'); return; }

    // 送信前にnonceを最新に更新（ページを長時間開いていても失敗しないよう）
    try {
        const refreshRes = await fetch(AJAX, {
            method:'POST',
            credentials:'same-origin',
            body: (() => { const f = new FormData(); f.append('action','tetsulog_refresh_nonce'); return f; })()
        });
        const refreshData = await refreshRes.json();
        if(refreshData.success && refreshData.data.nonce){
            const nonceInput = form.querySelector('[name="tl_nonce"]');
            if(nonceInput) nonceInput.value = refreshData.data.nonce;
        }
    } catch(e){ /* 更新失敗しても続行 */ }

    // 検出中なら少し待つ
    const hint = document.getElementById('tl-file-hint');
    if(hint && hint.textContent === '検出中...'){
        await new Promise(r => setTimeout(r, 2000));
    }

    this.disabled = true;
    this.innerHTML = '<div class="sp"></div> ' + (isEdit ? '更新中...' : '投稿中... (' + selOrientation + ')');

    const fd = new FormData();
    fd.append('action',               'submit_tetsulog_post');
    fd.append('tl_nonce',             form.querySelector('[name="tl_nonce"]').value);
    fd.append('tetsulog_type',        document.getElementById('tl-sel-type').value);
    fd.append('tetsulog_orientation', selOrientation);
    if(selFile) fd.append('tetsulog_file', selFile);  // 編集時はファイルなしでもOK
    fd.append('vehicle_name',         vname);
    fd.append('tetsulog_category',    form.querySelector('[name="tetsulog_category"]').value);
    fd.append('linked_addon_id',      form.querySelector('[name="linked_addon_id"]').value);
    fd.append('tetsulog_tags',        form.querySelector('[name="tetsulog_tags"]').value.trim());
    fd.append('tetsulog_description', (form.querySelector('[name="tetsulog_description"]')?.value || '').trim());
    if(isEdit) fd.append('edit_post_id', editId);  // 編集モードのみ

    try {
        const res  = await fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'});
        const data = await res.json();
        if(data.success){
            showAlert('ok', isEdit ? '更新しました！' : '投稿しました！ページを更新すると表示されます。');
            setTimeout(()=> location.reload(), 1400);
        } else {
            showAlert('err', data.data || (isEdit ? '更新に失敗しました。' : '投稿に失敗しました。'));
            this.disabled = false;
            this.innerHTML = isEdit
                ? '<i class="fa-solid fa-floppy-disk"></i> 更新する'
                : '<i class="fa-solid fa-paper-plane"></i> 投稿する';
        }
    } catch(err){
        showAlert('err','通信エラーが発生しました。');
        this.disabled = false;
        this.innerHTML = isEdit
            ? '<i class="fa-solid fa-floppy-disk"></i> 更新する'
            : '<i class="fa-solid fa-paper-plane"></i> 投稿する';
    }
});

/* ================================================================
   ログインポップアップを鉄ログページで無効化
   main.jsが使う可能性のある関数を空関数で上書き
   ================================================================ */
// DOM監視でポップアップが動的生成されても即非表示
(function(){
    const POPUP_SELECTORS = [
        '#login-modal','#login-popup','#login-overlay',
        '.login-modal','.login-popup','.login-overlay',
        '.login-required-modal','.login-required-popup',
        '#file-upload-login-modal','.upload-login-modal',
        '[id*="login-modal"]','[id*="login-popup"]',
    ];

    function hideLoginPopups(){
        POPUP_SELECTORS.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => {
                el.style.setProperty('display','none','important');
            });
        });
    }

    // DOMContentLoaded時に実行
    document.addEventListener('DOMContentLoaded', hideLoginPopups);

    // MutationObserverで動的生成されるポップアップも監視
    const mo = new MutationObserver(hideLoginPopups);
    mo.observe(document.body, {childList:true, subtree:true});

    // main.jsが使いそうなグローバル関数を無効化
    window.showLoginModal  = function(){};
    window.openLoginModal  = function(){};
    window.showLoginPopup  = function(){};
    window.requireLogin    = function(){ return false; };
    window.checkLogin      = function(){ return true; };
})();

})();
</script>

<?php get_footer(); ?>