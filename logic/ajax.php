<?php
if( ! defined( 'ABSPATH' ) ) {
	die("Silence is golden");
}

class BCTR_Cf7_Ajax {

    public static function GetDataPageLoad() {
        try {

            if( isset($_POST['nonce']) ) {

                self::BctCheckPermission();
                $params = array(
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'offset'         => 0,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                    'post_type'      => 'wpcf7_contact_form',
                );
                $cf7Forms = get_posts($params);

                $ret = array();
                foreach ( $cf7Forms as $k => $v ) {
                    if ( ! empty( $v->ID ) ) {
                        $ret[] = array(
                            'id'    => $v->ID,
                            'label' => $v->post_title,
                        );
                    }
                }

                wp_send_json_success($ret);
            }

            wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }

    }

    public static function GetContactData() {
        try {

            if( isset($_POST['nonce']) && isset($_POST['page']) && isset($_POST['size']) && isset($_POST['fid']) ) {
                global $wpdb;

                self::BctCheckPermission();

                $fid = intval($_POST['fid']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $page_size = isset($_POST['size']) ? intval($_POST['size']) : 10;
                $begin = $_POST['begin'] ? sanitize_text_field( $_POST['begin'] ) : date('Y-m-d', strtotime('-30 day'));
                $end = $_POST['end'] ? sanitize_text_field( $_POST['end'] ) : date('Y-m-d');
                $end .= " 23:59:59";
                $keyword = sanitize_text_field( $_POST['keyword'] );

                $row_id_sql = $wpdb->prepare('SELECT id FROM `' . self::GetTableRow() .'` WHERE cf7_type_id = %d AND `created` >= %s AND `created` <= %s', $fid, $begin, $end);

                $count_sql = $wpdb->prepare('SELECT COUNT(DISTINCT row_id) AS n FROM `'. self::GetTableRowColumns() .'` WHERE row_id IN ('. $row_id_sql .') And `column_value` LIKE "%%s%"', $keyword);

                $totalResult = $wpdb->get_results( $count_sql );
                $total = $totalResult[0] && $totalResult[0]->n ? $totalResult[0]->n : 0;

                if( $total > 0 ) {
                    $keywordSql = $wpdb->prepare(" And `column_value` LIKE '%%s%' ", $keyword);
                    $sql = $wpdb->prepare('SELECT * FROM `'. self::GetTableRowColumns() .'` WHERE row_id IN (SELECT * FROM (SELECT DISTINCT row_id FROM `'. self::GetTableRowColumns() .'` WHERE row_id IN ('. $row_id_sql .') '. $keywordSql .' ORDER BY `row_id` DESC limit %d, %d) AS a) ORDER BY row_id DESC', ( $page - 1 ) * $page_size, $page_size);

                    $data = $wpdb->get_results( $sql );
                    $data = self::ListAddId( self::GroupByRow( $data ) );
                } else {
                    $data = array();
                }

                wp_send_json_success(
                    array(
                        'total' => intval($total),
                        'list' => $data,
                        'settings' => self::GetSettings( $fid ),
                    )
                );
            }

            wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }
    }

    public static function EditRow() {
        try {

            if( isset( $_POST['nonce'] ) && isset( $_POST['row_id'] ) && isset( $_POST['columns'] ) ) {
                global $wpdb;

                self::BctCheckPermission();

                $row_id = intval( $_POST['row_id'] );

                foreach ( $_POST['columns'] as $key => $value ) {
                    $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::GetTableRowColumns() . ' SET `column_value` = %s WHERE `column_name` = %s AND `row_id` = %d', sanitize_text_field( $value ), sanitize_text_field( $key ), $row_id ) );
                }
                wp_send_json_success( array( 'mess' => __( 'Edit record success', 'bct-cf7' ) ) );
            }

            wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }
    }

    public static function DeleteRow() {
        try {

            if( isset( $_POST['nonce'] ) && isset( $_POST['ids'] ) ) {
                global $wpdb;

                self::BctCheckPermission();

                if( is_array( $_POST['ids'] ) && count( $_POST['ids'] ) > 0 ) {
                    //$ids = implode( ',', array_map( 'intval', $_POST['ids'] ) );

                    foreach ($_POST['ids'] as $id) {
                        $wpdb->query( $wpdb->prepare('DELETE FROM ' . self::GetTableRowColumns() . ' WHERE row_id = %d', intval($id)) );
                        $wpdb->query( $wpdb->prepare('DELETE FROM ' . self::GetTableRow() . ' WHERE id = %d', intval($id)) );
                    }

                    wp_send_json_success( array( 'mess' => __( 'Delete record success', 'bct-cf7' ) ) );
                }

                wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );
            }

            wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }
    }

    public static function SaveSetting( $setting ) {
        try {

            if( isset( $_POST['nonce'] ) && isset( $_POST['fid'] ) && isset( $_POST['settings'] ) ) {
                global $wpdb;

                self::BctCheckPermission();

                $fid = intval( $_POST['fid'] );
                $settings = json_decode( stripslashes( $_POST['settings'] ), true );

                add_option( 'bctr_cf7_settings' . $fid, bctr_sanitize_arr( $settings ), '', 'no' );
                update_option( 'bctr_cf7_settings' . $fid, bctr_sanitize_arr( $settings ) );

                wp_send_json_success(
                    array(
                        'mess' => __( 'Settings saved.', 'bct-cf7' ),
                    )
                );

            }

            wp_send_json_error( array( 'mess' => __( 'param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }
    }

    public static function ListAddId( $data ) {
        $ret = array();

        foreach ( $data as $id => $v ) {
            $v['id'] = $id;
            $v['rowKey'] = $id;
            array_push($ret, $v);
        }

        return $ret;
    }

    public static function GroupByRow( $data ) {
        $ret = array();

        foreach ( $data as $v ) {
            if ( !isset( $ret[ $v->row_id ] ) ) {
                $ret[ $v->row_id ] = array();
            }
            $ret[ $v->row_id ][ $v->column_name ] = self::FilterValue( $v->column_value );
        }

        return $ret;
    }

    public static function GetSettings( $fid ) {
        $fieldsDb = self::GetFormFields( $fid );
        $fieldSort = array();
        foreach ( $fieldsDb as $field ) {
            array_push($fieldSort, array(
                "field" => $field,
                "label" => $field,
                "show" => "1",
            ));
        }

        $settings = get_option( 'bctr_cf7_settings' . $fid );

        $defaultSettings = array(
            "fields" => $fieldSort,
            "table" => array(
                'header' => '1',
                'bordered' => '1',
                'showCheckbox' => '1',
                'ellipsis' => '1',
                'fixedRight' => '1',
                'tableSize' => 'small',
                'paginationBottom' => 'left',
            )
        );

        if( !$settings || !$settings["fields"] || !$settings["table"] ) {
            $settings = $defaultSettings;
            add_option( 'bctr_cf7_settings' . $fid, $settings, '', 'no' );
        }

        return $settings;
    }

    public static function AddFollowup() {
        try {

            if( isset($_POST['nonce']) && isset($_POST['row_id']) && isset($_POST['memo']) ) {
                global $wpdb;

                self::BctCheckPermission();

                $row_id = intval($_POST['row_id']);
                $memo = sanitize_text_field( $_POST['memo'] );
                $time = date( 'Y-m-d H:i:s' );
                $current_user = wp_get_current_user();

                if( !$current_user->ID ) {
                    wp_send_json_error( array( 'mess' => __( 'User error', 'bct-cf7' ) ) );
                }

                $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::GetTableFollowup() . ' (`row_id`, `user_id`, `memo`, `add_time`) VALUES (%d, %d, %s, %s)', $row_id, $current_user->ID, $memo, $time ) );
                $data_id = $wpdb->insert_id;

                wp_send_json_success(
                    array(
                        'mess' => __( 'Memo saved.', 'bct-cf7' ),
                        'data_id' => $data_id,
                    )
                );
            }

            wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }
    }

    public static function GetFollowupList() {
        try {

            if( isset($_POST['nonce']) && isset($_POST['row_id']) ) {
                global $wpdb;

                self::BctCheckPermission();

                $row_id = intval($_POST['row_id']);

                $sql = $wpdb->prepare("SELECT * FROM `". self::GetTableFollowup() ."` WHERE row_id = %d ORDER BY `id` DESC", $row_id);
                $data = $wpdb->get_results( $sql );

                if( count($data) > 0 ) {
                    foreach ($data as &$row) {
                        if( !isset($user[$row->user_id]) ) {
                            $user[$row->user_id] = $wpdb->get_row( $wpdb->prepare("SELECT * FROM `". $wpdb->prefix ."users` WHERE id = %d", intval($row->user_id)) );
                        }

                        $row->user_login = $user[ $row->user_id ]->user_login;
                    }
                }

                wp_send_json_success(
                    array(
                        'list' => $data,
                    )
                );
            }

            wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }
    }

    public static function DelFollowup() {
        try {

            if( isset( $_POST['nonce'] ) && isset( $_POST['ids'] ) ) {
                global $wpdb;

                self::BctCheckPermission();

                if( is_array( $_POST['ids'] ) && count( $_POST['ids'] ) > 0 ) {
                    foreach ($_POST['ids'] as $id) {
                        $wpdb->query( $wpdb->prepare('DELETE FROM ' . self::GetTableFollowup() . ' WHERE row_id = %d', intval($id)) );
                    }

                    wp_send_json_success( array( 'mess' => __( 'Delete record success', 'bct-cf7' ) ) );
                }

                wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );
            }

            wp_send_json_error( array( 'mess' => __( 'Param error', 'bct-cf7' ) ) );

        } catch ( \Error $ex ) {
            wp_send_json_error(
                array(
                    'mess' => __( 'Error', 'bct-cf7' ),
                    'error' => $ex,
                )
            );
        }
    }

    public static function FilterValue( $value ) {
        return preg_replace( "/\r?\n|\r/", '', $value );
    }

    public static function GetTableRow(){
        global $wpdb;

        return $wpdb->prefix . "bctr_cf7_row";
    }

    public static function GetTableRowColumns() {
        global $wpdb;

        return $wpdb->prefix . "bctr_cf7_row_columns";
    }

    public static function GetTableFollowup() {
        global $wpdb;

        return $wpdb->prefix . "bctr_cf7_followup";
    }

    public static function GetFormFields( $fid ) {
        global $wpdb;

        $fid = intval($fid);

        $sql = $wpdb->prepare('SELECT `column_name` FROM `'. self::GetTableRowColumns() .'` WHERE row_id IN (' .
            'SELECT id FROM `'. self::GetTableRow() .'` WHERE cf7_type_id = %d ' .
            ') AND `column_name` <> "visit_ip" GROUP BY `column_name`', $fid);
        $data = $wpdb->get_results( $sql );

        $fields = array();
        foreach ( $data as $v ) {
            $fields[ $v->column_name ] = $v->column_name;
        }
        if ( $fields ) {
            $fields = array_merge(array("row_id" => "id"), $fields);
        }
        return $fields;
    }

    public static function BctCheckPermission() {
        $nonce = sanitize_text_field( $_POST['nonce'] );
        if ( ! wp_verify_nonce( $nonce, 'bctr_cf7_nonce' ) ) {
            wp_send_json_error( array( 'mess' => 'Nonce is invalid' ) );
        }

        if( ! current_user_can('wpcf7_read_contact_forms') ) {
            wp_send_json_error( array( 'mess' => 'Not allowed visit this page', 'permission' => false ) );
        }
    }
}

function bctr_sanitize_arr( $arr ) {
    return is_array( $arr ) ? array_map( 'bctr_sanitize_arr', $arr ) : sanitize_text_field( $arr );
}