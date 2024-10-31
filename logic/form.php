<?php

if( ! defined( 'ABSPATH' ) ) {
	die("Silence is golden");
}

class BCTR_CF7_Form {
	// get cf7 post form data
	public static function GetPostData($contact_form) {
		if ( !isset( $contact_form->posted_data ) && class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();
			if ( $submission ) {
				$contact_form = (Object) array(
					'title' => $contact_form->title(),
					'posted_data' => $submission->get_posted_data(),
					'uploaded_files' => $submission->uploaded_files(),
					'contact_form' => $contact_form
				);
			}

			$contact_form = self::GetExtendInfo($contact_form);
			$contact_form = self::GetUploadFiles($contact_form);
		}

		return $contact_form;
	}

	// get more info
	public static function GetExtendInfo($contact_form) {
		$submission = WPCF7_Submission::get_instance();
		$contact_form->posted_data['post_time'] = date_i18n( 'Y-m-d H:i:s', $submission->get_meta( 'timestamp' ) );
		$contact_form->posted_data['visit_ip'] = isset( $_SERVER['X_FORWARDED_FOR'] )
			? sanitize_text_field( $_SERVER['X_FORWARDED_FOR'] )
			: sanitize_text_field( $_SERVER['REMOTE_ADDR'] );

		return $contact_form;
	}

	// upload file
	public static function GetUploadFiles($contact_form) {
		if ( is_object( $contact_form ) && isset( $contact_form->uploaded_files ) && count( $contact_form->uploaded_files ) > 0 ) {
			$upload_dir = wp_upload_dir();
			$folder = 'bool_cool_cf7_db';
			$dir_upload = $upload_dir['basedir'] . '/' . $folder;
			wp_mkdir_p($dir_upload);

			foreach ( $contact_form->uploaded_files as $k => $v ) {
				if( !empty($v) ) {
					$file = is_array($v) ? $v[0] : $v;
					$file_name = wp_unique_filename( $dir_upload, basename( $file ) );
					$dst_file  = $dir_upload . '/' . $file_name;
					if ( @copy($file, $dst_file) ) {
						$contact_form->posted_data[ $k ] = $upload_dir['baseurl'] . '/' . $folder . '/' . $file_name;
					}
				}
			}
		}

		return $contact_form;
	}
}