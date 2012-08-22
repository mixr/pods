<?php
/**
 * Name: Roles and Capabilities
 *
 * Menu Name: Roles &amp; Capabilities
 *
 * Description: Create and Manage WordPress User Roles and Capabilities; Uses the 'Members' plugin filters for additional plugin integrations; Portions of code based on the 'Members' plugin by Justin Tadlock
 *
 * Version: 1.0
 *
 * Developer Mode: on
 *
 * @package pods
 * @subpackage roles
 */

class Pods_Roles extends PodsComponent {

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 2.0.0
     */
    public function __construct () {
        add_filter( 'pods_roles_get_capabilities', array( $this, 'remove_deprecated_capabilities' ) );
    }

    /**
     * Enqueue styles
     *
     * @since 2.0.0
     */
    public function admin_assets () {
        wp_enqueue_style( 'pods-wizard' );
    }

    /**
     * Build admin area
     *
     * @param $options
     *
     * @since 2.0.0
     */
    public function admin ( $options, $component ) {
        global $wp_roles;

        $default_role = get_option( 'default_role' );

        $roles = array();

        foreach ( $wp_roles->role_objects as $key => $role ) {
            $roles[ $key ] = array(
                'id' => $key,
                'label' => $wp_roles->role_names[ $key ],
                'name' => $key,
                'capabilities' => count( (array) $role->capabilities ),
                'users' => sprintf( _n( '%s User', '%s Users', $this->count_users( $key ), 'pods' ), $this->count_users( $key ) )
            );

            if ( $default_role == $key )
                $roles[ $key ][ 'label' ] .= ' (site default)';

            if ( current_user_can( 'list_users' ) )
                $roles[ $key ][ 'users' ] = '<a href="' . admin_url( esc_url( 'users.php?role=' . $key ) ) . '">' . $roles[ $key ][ 'users' ] . '</a>';
        }

        $ui = array(
            'component' => $component,
            'data' => $roles,
            'total' => count( $roles ),
            'total_found' => count( $roles ),
            'icon' => PODS_URL . 'ui/images/icon32.png',
            'items' => 'Roles',
            'item' => 'Role',
            'fields' => array(
                'manage' => array(
                    'label' => array( 'label' => __( 'Label', 'pods' ) ),
                    'name' => array( 'label' => __( 'Name', 'pods' ) ),
                    'capabilities' => array( 'label' => __( 'Capabilities', 'pods' ) ),
                    'users' => array( 'label' => __( 'Users', 'pods' ) )
                )
            ),
            'actions_disabled' => array( 'duplicate', 'view', 'export' ),
            'actions_custom' => array(
                'add' => array( $this, 'admin_add' ),
                'edit' => array( $this, 'admin_edit' ),
                'delete' => array( $this, 'admin_delete' )
            ),
            'search' => false,
            'searchable' => false,
            'sortable' => false,
            'pagination' => false
         );

        if ( count( $roles ) < 2 )
            $ui[ 'actions_custom' ][] = 'delete';

        pods_ui( $ui );
    }

    function admin_add ( $obj ) {
        global $wp_roles;

        $capabilities = $this->get_capabilities();

        $defaults = $this->get_default_capabilities();

        $component = $obj->x[ 'component' ];

        $method = 'add'; // ajax_add

        echo pods_view( PODS_DIR . '/components/Roles/add.php', compact( array_keys( get_defined_vars() ) ) );
    }

    function admin_edit ( $obj ) {
        global $wp_roles;

        $capabilities = $this->get_capabilities();

        $role = array();

        foreach ( $wp_roles->role_objects as $key => $role ) {
            if ( $key != pods_var_raw( 'id', 'get', 0, null, true ) )
                continue;

            $role = array(
                'id' => $key,
                'name' => $wp_roles->role_names[ $key ],
                'capabilities' => $role->capabilities
            );
        }

        if ( empty( $role ) )
            return $obj->error( __( 'Role not found, cannot edit it.', 'pods' ) );

        $component = $obj->x[ 'component' ];

        $method = 'edit'; // ajax_edit

        echo pods_view( PODS_DIR . '/components/Roles/edit.php', compact( array_keys( get_defined_vars() ) ) );
    }

    function admin_delete ( $id, &$ui ) {
        global $wp_roles;

        $id = $_GET[ 'id' ];

        $default_role = get_option( 'default_role' );

        if ( $id == $default_role ) {
            return $ui->error( sprintf( __( 'You cannot remove the <strong>%s</strong> role, you must set a new default role for the site first.', 'pods' ), $ui->data[ $id ][ 'name' ] ) );
        }

        $wp_user_search = new WP_User_Search( '', '', $id );

        $users = $wp_user_search->get_results();

        if ( !empty( $users ) && is_array( $users ) ) {
            foreach ( $users as $user ) {
                $user_object = new WP_User( $user );

                if ( $user_object->has_cap( $id ) ) {
                    $user_object->remove_role( $id );
                    $user_object->set_role( $default_role );
                }
            }
        }

        remove_role( $id );

        $roles = array();

        foreach ( $wp_roles->role_objects as $key => $role ) {
            $roles[ $key ] = array(
                'id' => $key,
                'name' => ucwords( str_replace( '_', ' ', $key ) ),
                'capabilities' => count( (array) $role->capabilities )
            );
        }

        foreach ( $wp_roles->role_names as $role => $name ) {
            $roles[ $role ][ 'users' ] = 0;
            $roles[ $role ][ 'name' ] = $name;

            if ( $default_role == $role )
                $roles[ $role ][ 'name' ] .= ' (site default)';
        }

        $name = $ui->data[ $id ][ 'name' ];

        $ui->data = $roles;
        $ui->total = count( $roles );
        $ui->total_found = count( $roles );

        $ui->message( '<strong>' . $name . '</strong> ' . __( 'role removed from site.', 'pods' ) );
    }

    /**
     * Handle the Add Role AJAX
     *
     * @param $params
     */
    public function ajax_add ( $params ) {
        global $wp_roles;

        $role_name = pods_var_raw( 'role_name', $params );
        $role_label = pods_var_raw( 'role_label', $params );

        $params->capabilities = (array) pods_var_raw( 'capabilities', $params, array() );

        $capabilities = array();

        foreach ( $params->capabilities as $capability => $x ) {
            $capabilities[] = esc_attr( $capability );
        }

        if ( empty( $role_name ) )
            return pods_error( __( 'Role name is required', 'pods' ) );

        if ( empty( $role_label ) )
            return pods_error( __( 'Role label is required', 'pods' ) );

        if ( !isset( $wp_roles ) )
            $wp_roles = new WP_Roles();

        return $wp_roles->add_role( $role_name, $role_label, $capabilities );
    }

    /**
     * Handle the Edit Role AJAX
     *
     * @todo rename role_name
     * @todo rename role_label
     *
     * @param $params
     */
    public function ajax_edit ( $params ) {
        global $wp_roles;

        $capabilities = $this->get_capabilities();

        $role = array();

        foreach ( $wp_roles->role_objects as $key => $role ) {
            if ( $key != pods_var_raw( 'id', 'get', 0, null, true ) )
                continue;

            $role = array(
                'id' => $key,
                'name' => $wp_roles->role_names[ $key ],
                'capabilities' => $role->capabilities
            );
        }

        if ( empty( $role ) )
            return pods_error( __( 'Role not found, cannot edit it.', 'pods' ) );

        $capabilities_to_remove = array();

        foreach ( $role[ 'capabilitles' ] as $capability => $got ) {

        }
    }

    /**
     * Basic logic from Members plugin, it counts users of a specific role
     *
     * @param $role
     *
     * @return array
     */
    function count_users ( $role ) {
        $count_users = count_users();

        $avail_roles = array();

        foreach ( $count_users[ 'avail_roles' ] as $count_role => $count ) {
            $avail_roles[ $count_role ] = $count;
        }

        if ( empty( $role ) )
            return $avail_roles;

        if ( !isset( $avail_roles[ $role ] ) )
            $avail_roles[ $role ] = 0;

        return $avail_roles[ $role ];
    }

    function get_capabilities () {
        global $wp_roles;

        $default_caps = $this->get_wp_capabilities();

        $role_caps = array();

        foreach ( $wp_roles->role_objects as $key => $role ) {
            if ( is_array( $role->capabilities ) ) {
                foreach ( $role->capabilities as $cap => $grant ) {
                    $role_caps[ $cap ] = $cap;
                }
            }
        }

        $role_caps = array_unique( $role_caps );

        $plugin_caps = array(
            'pods_roles_list',
            'pods_roles_add',
            'pods_roles_delete',
            'pods_roles_edit',
            'restrict_content'
        );

        $capabilities = array_merge( $default_caps, $role_caps, $plugin_caps );

        // To support Members filters
        $capabilities = apply_filters( 'members_get_capabilities', $capabilities );

        $capabilities = apply_filters( 'pods_roles_get_capabilities', $capabilities );

        sort( $capabilities );
        $capabilities = array_unique( $capabilities );

        return $capabilities;
    }

    function get_wp_capabilities () {
        $defaults = array(
            'activate_plugins',
            'add_users',
            'create_users',
            'delete_others_pages',
            'delete_others_posts',
            'delete_pages',
            'delete_plugins',
            'delete_posts',
            'delete_private_pages',
            'delete_private_posts',
            'delete_published_pages',
            'delete_published_posts',
            'delete_users',
            'edit_dashboard',
            'edit_files',
            'edit_others_pages',
            'edit_others_posts',
            'edit_pages',
            'edit_plugins',
            'edit_posts',
            'edit_private_pages',
            'edit_private_posts',
            'edit_published_pages',
            'edit_published_posts',
            'edit_theme_options',
            'edit_themes',
            'edit_users',
            'import',
            'install_plugins',
            'install_themes',
            'list_users',
            'manage_categories',
            'manage_links',
            'manage_options',
            'moderate_comments',
            'promote_users',
            'publish_pages',
            'publish_posts',
            'read',
            'read_private_pages',
            'read_private_posts',
            'remove_users',
            'switch_themes',
            'unfiltered_html',
            'unfiltered_upload',
            'update_core',
            'update_plugins',
            'update_themes',
            'upload_files'
        );

        return $defaults;
    }

    function get_default_capabilities () {
        $capabilities = array(
            'read'
        );

        // To support Members filters
        $capabilities = apply_filters( 'members_new_role_default_capabilities', $capabilities );

        $capabilities = apply_filters( 'pods_roles_default_capabilities', $capabilities );

        return $capabilities;
    }

    function remove_deprecated_capabilities ( $capabilities ) {
        $deprecated_capabilities = array(
            'level_0',
            'level_1',
            'level_2',
            'level_3',
            'level_4',
            'level_5',
            'level_6',
            'level_7',
            'level_8',
            'level_9',
            'level_10'
        );

        $capabilities = array_diff( $capabilities, $deprecated_capabilities );

        return $capabilities;
    }
}