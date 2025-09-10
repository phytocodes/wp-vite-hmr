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
		return "{$scheme}://{$forwarded_host}/{$path}";
	}

	// WordPress のベース URL（置換元）
	function get_wp_base_url() {
		if (defined('WP_HOME') && WP_HOME) {
			return WP_HOME;
		}
		return 'http://localhost:8888';
	}

	// フィルターを一括登録
	$filters = [
		'site_url'                 => '',
		'home_url'                 => '',
		'stylesheet_directory_uri' => '',
		'template_directory_uri'   => '',
		'get_asset_directory_uri_filter' => '',
	];

	foreach ($filters as $filter => $path) {
		add_filter($filter, function ($url) use ($filter) {
			if (in_array($filter, ['stylesheet_directory_uri', 'template_directory_uri', 'get_asset_directory_uri_filter'])) {
				// テーマディレクトリ系は Vite のルートに置換
				return untrailingslashit(get_vite_base_url());
			} else {
				// site_url / home_url は WP のベースURLを Vite のURLに置換
				return str_replace(trailingslashit(get_wp_base_url()), get_vite_base_url(), $url);
			}
		});
	}

}
