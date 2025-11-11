<?php

/**
* Plugin Name: WP Vite HMR
* Description: VITE 構築用プラグイン
* Version: 0.0.1
* Author: phytocodes
* Author URI: https://github.com/phytocodes
* License: GPL-2.0-or-later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

function is_vite_dev_server() {
	return isset($_SERVER['HTTP_X_VITE_PROXY']) && $_SERVER['HTTP_X_VITE_PROXY'] === 'true';
}

if ( !is_admin() && is_vite_dev_server() ) {

	/* 開発モードでのViteアセットの読み込み
	---------------------------------------------------------- */
	function my_vite_dev_assets() {
		// テーマが'main'ハンドルで登録しているスタイルとスクリプトを解除
		wp_dequeue_style( 'main' );
		wp_dequeue_script( 'main' );

		// Vite用スクリプトを読み込み
		echo '<script type="module" crossorigin src="' . esc_url( home_url('/@vite/client') ) . '"></script>' . "\n";
		echo '<script type="module" crossorigin src="' . esc_url( home_url('/src/scripts/main.js') ) . '"></script>' . "\n";
	}
	add_action( 'wp_enqueue_scripts', 'my_vite_dev_assets', 20 );

	/* URL置換
	---------------------------------------------------------- */
	function get_vite_base_url($path = '') {
		// 環境変数または定数からホストとポートを取得
		$forwarded_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? "localhost:5173";
		$scheme = is_ssl() ? 'https' : 'http';
		return trailingslashit("{$scheme}://{$forwarded_host}/{$path}");
	}

	// WordPress のベース URL（置換元）
	function get_wp_base_url() {
		if (defined('WP_HOME') && WP_HOME) {
			return trailingslashit(WP_HOME);
		}
		return trailingslashit('http://localhost:8888');
	}

	function setup_vite_filters() {
		$filters = [
			'site_url',
			'home_url',
			'stylesheet_directory_uri',
			'template_directory_uri',
			'get_asset_directory_uri_filter',
		];

		foreach ($filters as $filter) {
			add_filter($filter, function ($url) use ($filter) {
				if (in_array($filter, ['stylesheet_directory_uri', 'template_directory_uri', 'get_asset_directory_uri_filter'])) {
					return untrailingslashit(get_vite_base_url());
				} else {
					return str_replace(get_wp_base_url(), get_vite_base_url(), $url);
				}
			});
		}
	}

	add_action('plugins_loaded', 'setup_vite_filters', 0);

	/* picture タグ用 srcset/WebP フィルタ
	---------------------------------------------------------- */
	add_filter('part_picture_args', function($args) {
		// テーマ側に generate_srcset_webp() が定義されている前提
		if (!function_exists('generate_srcset_webp')) return $args;

		$img_base_uri = defined('IMG_URI') ? IMG_URI : get_stylesheet_directory_uri() . '/assets/images/';
		$args['artDirectives'] = $args['artDirectives'] ?? [];
		$src = $args['src'] ?? null;
		if (!$src || empty($src['file'])) return $args;

		// --- PC用 ---
		$pc = generate_srcset_webp($src['file'], $img_base_uri);
		// 開発環境では WebP 無効
		$pc['webp_file'] = null;
		$args['src']['srcset'] = $pc['srcset'];
		$args['src']['webp_file'] = $pc['webp_file'];

		// --- SP用 ---
		foreach ($args['artDirectives'] as &$d) {
			if (empty($d['file'])) continue;
			$sp = generate_srcset_webp($d['file'], $img_base_uri);
			$sp['webp_file'] = null; // 開発環境では WebP 無効
			$d['srcset'] = $sp['srcset'];
			$d['webp_file'] = $sp['webp_file'];
		}

		return $args;
	});

}