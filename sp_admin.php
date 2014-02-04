<?php
if (!class_exists("sp_admin")) {

    /**
     * sp_admin class
     *
     * Handles many of the features and functions in the WordPress administrative
     * dashboard on the SmartPost settings page.
     */
    class sp_admin{

        /**
         * Includes CSS, JS, and AJAX classes to initialize the admin page.
         * Registers actions used by the admin page.
         *
         * @params none
         */
        static function init(){
            require_once('ajax/sp_adminAJAX.php');
            sp_adminAJAX::init();
            add_action( 'admin_menu', array('sp_admin', 'sp_admin_add_template_page') );
            add_action( 'admin_menu', array('sp_admin', 'sp_admin_add_category_page') );
            add_action( 'admin_enqueue_scripts', array('sp_admin', 'enqueueScripts') );

            //Load relevant classes for the admin page.
            $spTypes = sp_core::getTypesAndIDs();
            foreach($spTypes as $typeName => $typeID){
                $class = 'sp_cat' . $typeName;
                if(class_exists($class)){
                    $class::init();
                }
            }
        }

        /**
         * CSS for the admin pages
         */
        function enqueueCSS(){
            wp_register_style( 'sp_admin_css', plugins_url('/css/sp_admin.css', __FILE__) );
            wp_enqueue_style( 'sp_admin_css' );

            // Default WP styles
            wp_enqueue_style( 'media-views' );
            wp_enqueue_style( 'buttons' );
            wp_enqueue_style( 'wp-admin' );
        }

        /**
         * JS/CSS for the admin pages
         */
        function enqueueScripts($hook){

            if( 'toplevel_page_smartpost' != $hook && 'smartpost_page_sp-cat-page' != $hook ){
                return;
            }

            self::enqueueCSS();

            // load sp_admin.js only on the toplevel page
            wp_register_script( 'sp_admin_js', plugins_url('/js/sp_admin.js', __FILE__), array( 'post', 'postbox', 'jquery-dynatree' ) );
            wp_enqueue_script( 'post' );
            wp_enqueue_script( 'postbox' );
            wp_enqueue_script( 'sp_admin_js' );

            $typesAndIDs = sp_core::getTypesAndIDs();
            wp_localize_script( 'sp_admin_js', 'sp_admin', array(
                'SP_TYPES' => $typesAndIDs,
            ));
        }

        /**
         * Used in the WordPress action hook 'add_menu'.
         * Adds a top-level menu item to the Dashboard called SmartPost
         */
        function sp_admin_add_template_page() {
            add_menu_page( SP_PLUGIN_NAME, 'SmartPost', 'edit_dashboard', 'smartpost', array('sp_admin', 'sp_template_page'), null, null );
        }

        function sp_admin_add_category_page(){
            add_submenu_page( 'smartpost', 'Settings', 'Settings', 'edit_dashboard', 'sp-cat-page', array('sp_admin', 'sp_settings_page') );
        }

        /**
         * Renders all the component types as a HTML-draggable blocks.
         */
        public static function listCompTypes(){
            $types = sp_core::getTypes();
            ?>
            <div id="sp_compTypes">
                <?php foreach($types as $compType){ ?>
                    <div type-id="type-<?php echo $compType->id ?>"  title="<?php echo $compType->description ?>" class="catCompDraggable tooltip">
                        <h3><?php echo '<img src="' . $compType->icon . '" />' ?> <?php echo trim($compType->name) ?></h3>
                    </div>
                <?php } ?>
            </div>
        <?php
        }

        /**
         * Renders all the components of a given SmartPost-enabled category.
         * @param sp_category $sp_category
         */
        function listCatComponents($sp_category){
            $closed_meta_boxes = get_user_option( 'closedpostboxes_toplevel_page_smartpost' );
            $catComponents     = $sp_category->getComponents();
            if(!empty($catComponents)){
                foreach($catComponents as $component){
                    $component->render();

                    //handle meta box toggling
                    $compElemID = $component->getCompType() . '-' . $component->getID();
                    $key = array_search($compElemID, $closed_meta_boxes);
                    if($key !== false){
                        unset($closed_meta_boxes[$key]);
                    }
                }
                do_meta_boxes('toplevel_page_smartpost', 'normal', null);

                foreach($closed_meta_boxes as $box_id){
                    echo '<input type="text" class="postbox closed hide" id="' . $box_id . '" />';
                }
            }else{
                echo '<div id="normal-sortables" class="meta-box-sortables ui-sortable"></div>';
            }
        }

        /**
         * Renders a new category form that users can fill out
         */
        function renderNewTemplateForm(){
            //If a SP QP widget exists, do not check off "Add Widget" checkbox by default (minimizes confusion with unnecessary widgets)
            $sp_widget_instances = count( get_option( 'widget_sp_quickpostwidget' ) );
            ?>
            <div id="newTemplateForm">
                <form id="template_form" method="post" action="">
                    <table>
                        <tr>
                            <td>
                                <h4>Template Name <span style="color:red">*</span></h4>
                            </td>
                            <td>
                                <input type="text" class="regular-text" id="template_name" name="template_name" value="" />
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4>Template Description</h4>
                            </td>
                            <td>
                                <textarea class="regular-text" id="template_desc" name="template_desc" style="width: 100%; background: white;" rows="10" ></textarea>
                            </td>
                        </tr>
                        <?php
                            $nav_menus = wp_get_nav_menus();
                            if ( !empty( $nav_menus ) ){
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" checked="checked" id="add_to_menu" name="add_to_menu" /><label for="add_to_menu" id="add_to_menu_label" class="tooltip" title="Select the menu you'd like to add the template to. <br/> The template will then be accessible on the front-end via a menu item.">Add to menu</label>
                            </td>
                            <td>
                                <select id="wp_menus" name="wp_menus">
                                    <?php
                                    $nav_menus = wp_get_nav_menus();
                                    foreach($nav_menus as $menu){
                                        ?>
                                        <option id="menu-<?php echo $menu->term_id ?>" name="menu-<?php echo $menu->term_id ?>" value="<?php echo $menu->term_id ?>"><?php echo $menu->name ?></option>
                                        <?
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <?php } ?>
                        <tr>
                            <td>
                                <input type="checkbox" <?php echo $sp_widget_instances < 2 ? 'checked="checked"' : '' ?> id="add_widget" name="add_widget" /><label for="add_widget" id="add_widget_label" class="tooltip" title="Select the sidebar you'd like to add the SP QuickPost widget to.<br />This widget makes this template accessible to users on the front-end of the site.">Add SP widget</label>
                            </td>
                            <td>
                                <select id="widget_areas" name="widget_areas">
                                    <?php
                                    $sidebars = $GLOBALS['wp_registered_sidebars'];
                                    $recommended = "";
                                    $selected    = "";
                                    foreach($sidebars as $sidebar){
                                        if( defined('SP_CAT_SIDEBAR') ){
                                            if( $sidebar['id'] == SP_CAT_SIDEBAR ){
                                                $selected = 'selected="selected"';
                                                $recommended = ' (recommended)';
                                            }
                                        }
                                    ?>
                                    <option id="<?php echo $sidebar['id'] ?>" value="<?php echo $sidebar['id'] ?>" <?php echo $selected ?>><?php echo $sidebar['name'] . $recommended ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p style="color: red">* Required</p>
                    <button type="submit" class="button button-large" id="save_template">Save Template</button>
                </form>
            </div>
            <?php
        }

        /**
         * Renders the appropriate settings for a given category.
         * @param int $catID - the category ID
         * @param array $sp_categories - Array of category IDs that are SP enabled.
         */
        static function renderCatSettings($catID, $sp_categories){
            $sp_category = null;
            $category    = null;
            $cat_desc = null;
            $title = null;
            $icon  = null;
            if( in_array($catID, $sp_categories) ){
                $sp_category = new sp_category(null, null, $catID);
                $title = $sp_category->getTitle();
                $icon  = wp_get_attachment_image($sp_category->getIconID(), null, null, array('class' => 'category_icon'));
                $cat_desc = $sp_category->getDescription();
            }else{
                $category = get_category($catID);
                $title = $category->cat_name;
            }
            ?>
            <h2 class="category_title">
                <a href="<?php echo admin_url('edit-tags.php?action=edit&taxonomy=category&tag_ID=' . $catID . '&post_type=post') ?>">
                    <?php echo $icon . ' ' . $title ?>
                </a>
            </h2>
            <?php echo '<p>' . $cat_desc . '</p>'; ?>
            <?php
                if(!is_null($sp_category)){
                ?>
                    <input type="checkbox" id="sp_enabled" checked /> <label for="sp_enabled">Uncheck to disable SmartPost for this category.</label>
                <?php
                } else {
                ?>
                    <input type="checkbox" id="sp_enabled" /> <label for="sp_enabled">Check to enable SmartPost for this category.</label>
                <?php
                }
            ?>
            <input type="hidden" name="catID" id="catID" value="<?php echo $catID ?>" />
            <?php
        }

        /**
         * Build an object that represents the category hierarchy with added smartpost components.
         * @param $args - $args used in get_category query
         * @param int $parent - The "root" parent node of where to start the query
         * @param bool $include_parent - Whether to include the parent in the resulting array
         * @return array
         */
        public static function buildSPDynaTree($args, $parent = 0, $include_parent = false){

            if($include_parent){
                $parentCat  = get_category( $parent );
                $categories = array( $parentCat );
            } else {
                $args['parent'] = $parent;
                $categories     = get_categories($args);
            }

            $sp_categories = get_option( "sp_categories" );
            $catTree =  array();

            foreach( $categories as $category ) {

                $node = new stdClass();

                $node->title    = $category->name;
                $node->key      = 'cat-' . $category->term_id;
                $node->isFolder = true;
                $node->catID    = $category->term_id;
                $node->href     = admin_url('admin.php?page=smartpost&catID=' . $category->term_id);
                $node->target   = '_self';

                if( in_array( $category->term_id, $sp_categories ) ){

                    $sp_category = new sp_category( null, null, $category->term_id );

                    $icon = wp_get_attachment_url( $sp_category->getIconID() );
                    $node->icon = $icon ? $icon : null;

                    $components = $sp_category->getComponents();

                    if( !empty($components) ){
                        $node->compCount = count($components);

                        $compNodes = array();
                        foreach( $components as $comp ) {
                            $compNode = new stdClass();
                            $compNode->title  = $comp->getName();
                            $compNode->key    = 'comp-' .  $comp->getID();
                            $compNode->icon   = $comp->getIcon() ? $comp->getIcon() : null;
                            $compNode->compID = $comp->getID();
                            array_push($compNodes, $compNode);
                        }
                    }
                }else{
                    $node->addClass = 'disableSPSortable';
                }

                $node->children = sp_admin::buildSPDynaTree($args, $category->term_id);

                if( !empty($compNodes) ){
                    $node->children = array_merge_recursive($compNodes, $node->children);
                    $compNodes = null;
                }

                array_push( $catTree, $node );
            }

            return $catTree;
        }

        /**
         * Processes incoming messages from $_GET parameter
         * @param $msg
         * @return string
         */
        private static function sp_message( $msg ){
            $msg = (string) $msg;

            switch( $msg ){
                case 'new_cat':
                    $catID = (int) $_GET['catID'];
                    if( !empty($catID) ){
                        $newTemplate = get_category( $catID );
                        $newTemplateName = $newTemplate->name;
                        return '<p>Template \'' . $newTemplateName . '\' successfully created! To modify the way it appears on the menu, click <a href="' . admin_url('nav-menus.php') . '">here</a>. To view the template on your site, click <a href="' . get_category_link($catID) . '" target="_blank">here</a>.</p>';
                    }else{
                        return '';
                    }
                default:
                    return $msg;
            }
        }

        /**
         * Renders the dashboard admin page for the SmartPost plugin.
         * @see sp_admin::sp_admin_add_page()
         * @todo Use add_meta_box() instead of hard-coding meta boxes - specifically for the template-tree and component-palette widgets.
         */
        function sp_template_page(){
            if ( !current_user_can('edit_dashboard') )  {
                wp_die( __('You do not have sufficient permissions to access this page.') );
            }
            $categories    = get_categories( array( 'orderby' => 'name','order' => 'ASC', 'hide_empty' => 0 ) );
            $sp_categories = get_option('sp_categories');
            $catID         = empty($_GET['catID']) ? $categories[0]->term_id : (int) $_GET['catID'];
            $sp_category   = new sp_category(null, null, $catID);

            $error_msg  =  self::sp_message( $_GET['error_msg'] );
            $update_msg =  self::sp_message( $_GET['update_msg'] );

            ?>

            <div class="wrap">
                <div class="error" <?php echo empty( $error_msg ) ? 'style="display: none;"' : ''; ?>><span id="sp_errors"><?php echo $error_msg ?></span><span class="hideMsg sp_xButton" title="Ok, got it"></span><div class="clear"></div></div>
                <div class="updated" <?php echo empty( $update_msg ) ? 'style="display: none;"' : ''; ?>><span id="sp_update"><?php echo $update_msg ?></span><span class="hideMsg sp_xButton" title="Ok, got it"></span><div class="clear"></div></div>
                <h2><img src="<?php echo SP_IMAGE_PATH ?>/sp-icon.png" style="height: 17px;" /> <span style="color: #89b0ff;">Smart<span style="color: #07e007">Post</span> Templates</span> <button id="newTemplateButton" class="button button-primary button-large" title="Create a new category template">New Template</button></h2>
                <?php self::renderNewTemplateForm(); ?>
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content" style="margin-bottom: 0;">
                            <div id="category_settings" class="postbox">
                                <div id="the_settings">
                                    <span id="delete-<?php echo $catID ?>" class="deleteCat sp_xButton" data-cat-id="<?php echo $catID ?>" title="Delete Template"></span>
                                    <?php self::renderCatSettings($catID, $sp_categories); ?>
                                    <div class="clear"></div>
                                </div><!-- end #the_settings -->
                                <div class="clear"></div>
                            </div><!-- end #category_settings -->
                        </div><!-- end #post-body-content -->

                        <div id="postbox-container-1" class="postbox-container">
                            <div id="sp_cat_list" class="postbox" style="display: block;">
                                <h3 class="hndle" style="cursor: default"><span>SmartPost Templates</span></h3>
                                <div class="inside">
                                    <div id="sp_catTreeSettings">
                                        <p id="expandAll">Expand/Collapse All</p>
                                    </div>
                                    <div id="sp_catTree"></div>
                                </div>
                            </div><!-- end sp_cat_list -->

                            <div id="sp_components" class="postbox" style="display: block;">
                                <h3 class="hndle" style="cursor: default;"><span>SmartPost Components</span></h3>
                                <div class="inside">
                                    <p>Drag the below components to the template on the left:</p>
                                    <?php self::listCompTypes() ?>
                                </div>
                            </div><!-- end sp_components -->
                        </div><!-- end #postbox-container-1 -->

                        <?php
                        if( in_array($catID, $sp_categories) ){
                        ?>
                        <div id="postbox-container-2" class="postbox-container">
                            <?php self::listCatComponents($sp_category) ?>
                        </div><!-- end #postbox-container-2 -->
                        <?php
                            //handle toggling for meta boxes
                            wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
                        }
                        ?>
                    </div><!-- end #post-body -->
                </div><!-- end #poststuff -->
        <?php
        }

        /**
         * Renders the settings page in the dashboard. Contains global configuration/settings for each components.
         * @see sp_catComponent::renderGlobalOptions
         */
        function sp_settings_page(){
            $components = sp_core::getTypes();
            $currCompID = (int) empty( $_GET['compID'] ) ? $components[0]->id : $_GET[ 'compID' ] ;
            $currCompType = sp_core::getType( $currCompID );
            $currCompClass = 'sp_cat' . $currCompType->name;

            ?>
            <div class="wrap">
                <div class="error" <?php echo empty( $error_msg ) ? 'style="display: none;"' : ''; ?>><span id="sp_errors"><?php echo $error_msg ?></span><span class="hideMsg sp_xButton" title="Ok, got it"></span><div class="clear"></div></div>
                <div class="updated" <?php echo empty( $update_msg ) ? 'style="display: none;"' : ''; ?>><span id="sp_update"><?php echo $update_msg ?></span><span class="hideMsg sp_xButton" title="Ok, got it"></span><div class="clear"></div></div>
            <h2><img src="<?php echo SP_IMAGE_PATH ?>/sp-icon.png" style="height: 17px;" /> <span style="color: #89b0ff;">Smart<span style="color: #07e007">Post</span> Templates</span> - Settings</h2>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">

                    <div id="postbox-container-1" class="postbox-container">
                        <div id="sp_cat_list" class="postbox">
                            <div class="handlediv" title="Click to toggle"><br></div>
                            <h3 class="hndle" style="cursor: default"><span>SmartPost Components:</span></h3>
                            <div class="inside">
                                <?php
                                foreach($components as $comp){
                                    echo '<a href="' . admin_url('admin.php?page=sp-cat-page') . '&compID=' . $comp->id . '" style="text-decoration: none;"><img src="' . $comp->icon . '" /> ' . $comp->name . '</a><br />';
                                }
                                ?>
                            </div>
                        </div><!-- end sp_cat_list -->
                    </div><!-- end #postbox-container-1 -->

                    <div id="post-body-content">
                        <div class="postbox component-settings">
                            <h2><?php echo $currCompType->name ?> Component Settings</h2>
                            <p>Description: <?php echo $currCompType->description ?></p>
                            <div id="the_settings">
                                <?php
                                    if( class_exists( $currCompClass ) ){
                                        $settings = $currCompClass::globalOptions();
                                        if( $settings !== false ){
                                            echo $settings;
                                        }
                                    }
                                ?>
                            </div>
                            <div class="clear"></div>
                        </div><!-- end #category_settings -->
                    </div>

                </div><!-- end #post-body -->
            </div><!-- end #poststuff -->
        <?php
        }
    }
}
?>