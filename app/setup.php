<?php

namespace App;

use Roots\Sage\Container;
use Roots\Sage\Assets\JsonManifest;
use Roots\Sage\Template\Blade;
use Roots\Sage\Template\BladeProvider;

/**
 * Theme assets
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('sage/main.css', asset_path('styles/main.css'), false, null);
    wp_enqueue_script('sage/main.js', asset_path('scripts/main.js'), ['jquery'], null, true);

    if (is_single() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}, 100);

/**
 * Theme setup
 */
add_action('after_setup_theme', function () {
    /**
     * Enable features from Soil when plugin is activated
     * @link https://roots.io/plugins/soil/
     */
    add_theme_support('soil-clean-up');
    add_theme_support('soil-jquery-cdn');
    add_theme_support('soil-nav-walker');
    add_theme_support('soil-nice-search');
    add_theme_support('soil-relative-urls');

    /**
     * Enable plugins to manage the document title
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
     */
    add_theme_support('title-tag');

    /**
     * Register navigation menus
     * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
     */
    register_nav_menus([
        'primary_navigation' => __('Primary Navigation', 'sage')
    ]);

    /**
     * Enable post thumbnails
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support('post-thumbnails');

    /**
     * Enable HTML5 markup support
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#html5
     */
    add_theme_support('html5', ['caption', 'comment-form', 'comment-list', 'gallery', 'search-form']);

    /**
     * Enable selective refresh for widgets in customizer
     * @link https://developer.wordpress.org/themes/advanced-topics/customizer-api/#theme-support-in-sidebars
     */
    add_theme_support('customize-selective-refresh-widgets');

    /**
     * Use main stylesheet for visual editor
     * @see resources/assets/styles/layouts/_tinymce.scss
     */
    add_editor_style(asset_path('styles/main.css'));
}, 20);

/**
 * Register sidebars
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3>',
        'after_title'   => '</h3>'
    ];
    register_sidebar([
        'name'          => __('Primary', 'sage'),
        'id'            => 'sidebar-primary'
    ] + $config);
    register_sidebar([
        'name'          => __('Footer', 'sage'),
        'id'            => 'sidebar-footer'
    ] + $config);
});

/**
 * Updates the `$post` variable on each iteration of the loop.
 * Note: updated value is only available for subsequently loaded views, such as partials
 */
add_action('the_post', function ($post) {
    sage('blade')->share('post', $post);
});

/**
 * Setup Sage options
 */
add_action('after_setup_theme', function () {
    /**
     * Add JsonManifest to Sage container
     */
    sage()->singleton('sage.assets', function () {
        return new JsonManifest(config('assets.manifest'), config('assets.uri'));
    });

    /**
     * Add Blade to Sage container
     */
    sage()->singleton('sage.blade', function (Container $app) {
        $cachePath = config('view.compiled');
        if (!file_exists($cachePath)) {
            wp_mkdir_p($cachePath);
        }
        (new BladeProvider($app))->register();
        return new Blade($app['view']);
    });

    /**
     * Create @asset() Blade directive
     */
    sage('blade')->compiler()->directive('asset', function ($asset) {
        return "<?= " . __NAMESPACE__ . "\\asset_path({$asset}); ?>";
    });
});

function routerLink($path) {
  $path = str_replace(home_url(), '', $path);
  return $path;
}

// WP localize Script
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('sage/main.js', asset_path('scripts/main.js'), ['jquery'], null, true);
    $routes = [];

    //Dynamic Vue Router
    $pages = get_posts('post_type=page&nopaging=true');
    foreach($pages as $page) {
      array_push($routes, [
        'path' => routerLink(get_the_permalink($page->ID)), 
        'component' => 'Page', 
        'props' => ['pageId' => $page->ID, 'pageType' => 'page']
        ]);
    }

    array_push($routes, [
        'path' => '*', 
        'component' => 'Page404', 
        'props' => ['pageType' => '404']
    ]);

    $ajax_params = [
        'url' => home_url(),
        'routes' => $routes,
        'name' => get_bloginfo('name')
    ];

    wp_localize_script('sage/main.js', 'wp', $ajax_params);
}, 100);

// ACF options page
if(function_exists('acf_add_options_page')) {
    acf_add_options_page();
}

// REST API Endpoints
function get_global_options() {
    $menu = get_field('menu', 'option');
    $menuItems = wp_get_nav_menu_items($menu);
    $items = [];
    $error_headline = get_field('headline', 'option') ? get_field('headline', 'option') : '404 Error';
    $error_text = get_field('text', 'option') ? get_field('text', 'option') : 'Sorry, this page is not available';

    foreach ($menuItems as $menuItem) {
        array_push($items,  array('title' => $menuItem->title, 'slug' => routerLink($menuItem->url)));
    }

    $data = [
        'logo' => get_field('logo', 'option'),
        'menu' => $items,
        'error_headline' => $error_headline,
        'error_text' => $error_text
    ];

    return $data;
}

function get_page_options($data) {
  $the_title = get_the_title($data['id']);
  $the_content = get_post_field('post_content', $data['id']);
  $menu = get_field('menu', $data['id']);
  $menuItems = wp_get_nav_menu_items($menu);
  $gallery = get_field('gallery', $data['id']);
  $videos = get_field('videos', $data['id']);
  $featured = has_post_thumbnail($data['id']) ? get_the_post_thumbnail($data['id'], 'full') : null;
  $items = [];
  $gallery_photos = [];
  $video_items = [];

  if ($menu) {
    foreach ($menuItems as $menuItem) {
      array_push($items,  [
        'title' => $menuItem->title, 
        'slug' => routerLink($menuItem->url)
      ]);
    }
  }

  if($gallery) {
    foreach ($gallery as $gallery_item) {
      array_push($gallery_photos, [
        'gallery_photo' => $gallery_item['photo']
      ]);
    }
  }

  if ($videos) {
      foreach($videos as $video_item) {
          array_push($video_items, [
              'video_html' => $video_item['video']
          ]);
      }
  }

  $data = [
    'the_title' => $the_title,
    'the_content' => $the_content,
    'featured' => $featured,    
    'menu' => $items,
    'gallery' => $gallery_photos,
    'videos' => $video_items
  ];

  return $data;
}

add_action('rest_api_init', function() {
    register_rest_route('as/v1', 'global', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ .'\\get_global_options'
    ]);

    register_rest_route('as/v1', 'pages/(?P<id>\d+)', [
      'methods' => 'GET',
      'callback' => __NAMESPACE__ .'\\get_page_options'
  ]);
});

//Disable redirects let Vue handle routing
remove_action('template_redirect', 'redirect_canonical');