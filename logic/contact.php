<?php

if( ! defined( 'ABSPATH' ) ) {
	die("Silence is golden");
}

class BCTR_Cf7_Subscriber {
	public static function InitTable() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_row = $wpdb->prefix . 'bctr_cf7_row';

		if ( $wpdb->get_var( $wpdb->prepare("show tables like %s", $table_row) ) != $table_row ) {
			$sql = 'CREATE TABLE ' . $table_row . ' (
			            `id` int(11) NOT NULL AUTO_INCREMENT,
			            `cf7_type_id` int(11) NOT NULL,
			            `created` timestamp NOT NULL,
			            UNIQUE KEY id (id)
			            ) ' . $charset_collate . ';';
			dbDelta( $sql );
		}

		$table_columns = $wpdb->prefix . 'bctr_cf7_row_columns';
		if ( $wpdb->get_var( $wpdb->prepare("show tables like %s", $table_columns) ) != $table_columns ) {
			$sql = 'CREATE TABLE ' . $table_columns . ' (
			            `id` int(11) NOT NULL AUTO_INCREMENT,
			            `row_id` int(11) NOT NULL,
			            `column_name` varchar(250),
			            `column_value` text,
			            UNIQUE KEY id (id)
			            ) ' . $charset_collate . ';';
			dbDelta( $sql );
		}

		$table_followup = $wpdb->prefix . 'bctr_cf7_followup';
		if( $wpdb->get_var( $wpdb->prepare("show tables like %s", $table_followup) ) != $table_followup ) {
			$sql = 'CREATE TABLE ' . $table_followup . ' (
			            `id` int(11) NOT NULL AUTO_INCREMENT,
			            `row_id` int(11) NOT NULL,
			            `user_id` int(11) NOT NULL,
			            `memo` text NOT NULL,
			            `add_time` timestamp NOT NULL,
			            UNIQUE KEY id (id)
			            ) ' . $charset_collate . ';';
			dbDelta( $sql );
		}
	}

	public static function AddContact($contact_form, &$abort, $submission) {
		global $wpdb;

		$service = WPCF7_Stripe::get_instance();
		if ( $service->is_active() ) {
			if ( empty( $submission->payment_intent ) ) {
				return;
			}
		}

		$cf7_type_id = $contact_form->id();
		$contact_form = BCTR_CF7_Form::GetPostData($contact_form);

		// insert row
		$table_row = $wpdb->prefix . 'bctr_cf7_row';
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . $table_row . '(`cf7_type_id`, `created`) VALUES (%d, %s)', $cf7_type_id, date( 'Y-m-d H:i:s' ) ) );
		$row_id = $wpdb->insert_id;

		// insert column
		$no_need_save = ['_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_is_ajax_call'];
		foreach ( $contact_form->posted_data as $k => $v ) {
			if ( !in_array( $k, $no_need_save ) ) {
				$v = is_array( $v ) ? implode( "\n", $v ) : $v;

				if ( !empty( $v ) ) {
					$table_columns = $wpdb->prefix . 'bctr_cf7_row_columns';
					$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . $table_columns . '(`row_id`, `column_name`, `column_value`) VALUES (%d, %s,%s)', $row_id, $k, $v ) );
				}
			}
		}
	}
}