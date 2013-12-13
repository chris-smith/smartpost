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

            //Default WP styles
            wp_enqueue_style( 'buttons' );
            wp_enqueue_style( 'wp-admin' );
        }

        /**
         * JS/CSS for the admin pages
         */
        function enqueueScripts($hook){
            if('toplevel_page_smartpost' != $hook){
                return;
            }
            self::enqueueCSS();

            wp_register_script( 'sp_admin_globals', plugins_url('/js/sp_admin_globals.js', __FILE__), array( 'jquery') );
            wp_register_script( 'sp_admin_js', plugins_url('/js/sp_admin.js', __FILE__), array('sp_admin_globals', 'post', 'postbox'));
            wp_enqueue_script( 'post' );
            wp_enqueue_script( 'postbox' );
            wp_enqueue_script( 'sp_admin_globals' );
            wp_localize_script( 'sp_admin_globals', 'sp_admin', array(
                    'ADMIN_NONCE' => wp_create_nonce( 'sp_admin_nonce'),
                    'ADMIN_URL'	  => admin_url( 'admin.php'),
                    'PLUGIN_PATH' => PLUGIN_PATH,
                    'IMAGE_PATH'  => IMAGE_PATH )
            );
            wp_enqueue_script( 'sp_admin_js' );
        }

        /**
         * Used in the WordPress action hook 'add_menu'.
         * Adds a top-level menu item to the Dashboard called SmartPost
         */
        function sp_admin_add_template_page() {
            add_menu_page( PLUGIN_NAME, 'SmartPost', 'edit_users', 'smartpost', array('sp_admin', 'sp_template_page'), null, null );
        }

        function sp_admin_add_category_page(){
            add_submenu_page( 'smartpost', 'Settings', 'Settings', 'edit_users', 'sp-cat-page', array('sp_admin', 'sp_component_page') );
        }

        /**
         * Renders all the component types as a HTML-draggable blocks.
         */
        public static function listCompTypes(){
            $types = sp_core::getTypes();
            ?>
            <div id="sp_compTypes">
                <?php foreach($types as $compType){ ?>
                    <div type-id="type-<?php echo $compType->id ?>"  alt="<?php echo $compType->description ?>" class="catCompDraggable">
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
                    $key        = array_search($compElemID, $closed_meta_boxes);
                    if($key !== false){
                        unset($closed_meta_boxes[$key]);
                    }
                }
                do_meta_boxes('toplevel_page_smartpost', 'normal', null);

                foreach($closed_meta_boxes as $box_id){
                    echo '<input type="text" class="postbox closed hide" id="' . $box_id . '" />';
                }
            }else{
                echo "<div id='normal-sortables' class='meta-box-sortables ui-sortable'></div>";
            }
        }

        /**
         * Renders a new category form that users can fill out
         */
        function renderCategoryForm(){
            ?>
            <div id="newCategoryForm">
                <form id="cat_form" method="post" action="">
                    <table>
                        <tr>
                            <td>
                                <h4>Category Name <span style="color:red">*</span></h4>
                            </td>
                            <td>
                                <input type="text" class="regular-text" id="cat_name" name="cat_name" value="" />
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <h4>Category Description</h4>
                            </td>
                            <td>
                                <input type="text" class="regular-text" id="category_description" name="category_description" value="" />
                            </td>
                        </tr>
                        <tr>
                            <td><h4>Category Icon</h4></td>
                            <td>
                                <input type="file" id="category_icon" name="category_icon">
                            </td>
                        </tr>
                    </table>
                    <p style="color: red">* Required</p>
                    <button class="button button-large" id="">Save Template</button>
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
                    <input type="checkbox" id="sp_enabled" checked /> <label for="sp_enabled">Click to disable SmartPost for this category.</label>
                <?php
                }else{
                ?>
                    <input type="checkbox" id="sp_enabled" /> <label for="sp_enabled">Click to enable SmartPost for this category.</label>
                <?php
                }
            ?>
            <input type="hidden" name="catID" id="catID" value="<?php echo $catID ?>" />
            <?php
        }

        /**
         * Build an object that represents the category hierarchy
         * with added smartpost components.
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
         * Renders the dashboard admin page for the SmartPost plugin.
         * @see sp_admin::sp_admin_add_page()
         */
        function sp_template_page(){
            if (!current_user_can('manage_options'))  {
                wp_die( __('You do not have sufficient permissions to access this page.') );
            }
            $categories    = get_categories(array('orderby' => 'name','order' => 'ASC', 'hide_empty' => 0));
            $sp_categories = get_option('sp_categories');
            $catID         = empty($_GET['catID']) ? $categories[0]->term_id : (int) $_GET['catID'];
            $sp_category   = new sp_category(null, null, $catID);
            ?>

            <div class="wrap">
            <div id="sp_errors"></div>
            <h2><?php echo PLUGIN_NAME . ' Templates' ?></h2>

            <button id="newCatButton" class="button button-primary button-large" title="Create a new category template">New Template</button>
            <?php self::renderCategoryForm(); ?>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">

                    <div id="post-body-content" style="margin-bottom: 0;">
                        <div id="category_settings" class="postbox">
                            <div id="setting_errors"></div>
                            <div id="the_settings">
                                <?php self::renderCatSettings($catID, $sp_categories); ?>
                                <div class="clear"></div>
                            </div><!-- end #the_settings -->
                            <div class="clear"></div>
                        </div><!-- end #category_settings -->
                    </div>

                    <div id="postbox-container-1" class="postbox-container">

                        <div id="sp_cat_list" class="postbox" style="display: block;">
                            <div class="handlediv" title="Click to toggle"><br></div>
                            <h3 class="hndle" style="cursor: default"><span>SmartPost Templates</span></h3>
                            <div class="inside">
                                <?php //self::renderCatTree(); ?>
                                <div id="sp_catTree"></div>
                            </div>
                        </div><!-- end sp_cat_list -->

                        <div id="sp_components" class="postbox" style="display: block;">
                            <div class="handlediv" title="Click to toggle"><br></div>
                            <h3 class="hndle" style="cursor: default;"><span>SmartPost Components</span></h3>
                            <div class="inside">
                                <p>Drag the below components to the template on the left:</p>
                                <?php self::listCompTypes() ?>
                            </div>
                        </div><!-- end sp_components -->

                    </div><!-- end #postbox-container-1 -->
                    <?php
                    if(in_array($catID, $sp_categories)){
                    ?>
                    <div id="postbox-container-2" class="postbox-container">
                        <?php self::listCatComponents($sp_category) ?>
                    </div><!-- end #postbox-container-1 -->
                    <?php
                        //handle toggling for meta boxes
                        wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
                    }
                    ?>
                </div><!-- end #post-body -->
            </div><!-- end #poststuff -->
        <?php
        }

        function sp_component_page(){
            ?>
            <div class="wrap">
            <h2><?php echo PLUGIN_NAME ?> Component Settings</h2>
            <?php
                $components = sp_core::getTypes();
                foreach($components as $comp){
                    echo $comp->name . '<br />';
                }
            ?>
        <?php
        }
    }
}
?>