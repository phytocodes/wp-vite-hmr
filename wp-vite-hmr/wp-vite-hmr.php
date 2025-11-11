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
			'theme_assets_uri',
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

	/* picture 用フィルタ
	---------------------------------------------------------- */
	function vite_filter_part_picture_args($args) {
		$img_base_uri = IMG_URI;

		// PC
		if (!empty($args['src']['file'])) {
			$file = $args['src']['file'];
			$args['src']['srcset'] = $img_base_uri . '/' . $file;
			$args['src']['webp_file'] = null;
		}

		// SP / artDirectives
		foreach ($args['artDirectives'] as &$d) {
			if (!empty($d['file'])) {
				$d['srcset'] = $img_base_uri . '/' . $d['file'];
				$d['webp_file'] = null;
			}
	}

		return $args;
	}

	add_filter('part_picture_args', 'vite_filter_part_picture_args', PHP_INT_MAX);

}