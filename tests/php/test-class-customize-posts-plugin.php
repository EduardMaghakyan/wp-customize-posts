<?php
/**
 * Tests for Customize_Posts_Plugin.
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_Customize_Posts_Plugin
 */
class Test_Customize_Posts_Plugin extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Customize_Posts_Plugin
	 */
	public $plugin;

	/**
	 * Customize Manager instance.
	 *
	 * @var WP_Customize_Manager
	 */
	public $wp_customize;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->plugin = $GLOBALS['customize_posts_plugin'];
		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		$GLOBALS['wp_customize'] = new WP_Customize_Manager(); // WPCS: global override ok.
		$this->wp_customize = $GLOBALS['wp_customize'];
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		$this->wp_customize = null;
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['wp_scripts'] );
		parent::tearDown();
	}

	/**
	 * Test constructor.
	 *
	 * @see Customize_Posts_Plugin::__construct()
	 */
	public function test_construct() {
		$plugin = new Customize_Posts_Plugin();
		$plugin_data = get_plugin_data( realpath( dirname( __FILE__ ) . '/../../customize-posts.php' ) );
		$this->assertEquals( $plugin->version, $plugin_data['Version'] );
		$this->assertInstanceOf( 'Edit_Post_Preview', $plugin->edit_post_preview );
		$this->assertEquals( 11, has_action( 'wp_default_scripts', array( $plugin, 'register_scripts' ) ) );
		$this->assertEquals( 41, has_action( 'admin_bar_menu', array( $plugin, 'add_admin_bar_customize_link_queried_object_autofocus' ) ) );
		$this->assertEquals( 11, has_action( 'wp_default_styles', array( $plugin, 'register_styles' ) ) );
		$this->assertEquals( 10, has_action( 'user_has_cap', array( $plugin, 'grant_customize_capability' ) ) );
		$this->assertEquals( 100, has_action( 'customize_loaded_components', array( $plugin, 'filter_customize_loaded_components' ) ) );
	}

	/**
	 * Test constructor with admin notice.
	 *
	 * @see Customize_Posts_Plugin::__construct()
	 */
	public function test_construct_admin_notice() {
		$stub = $this->getMockBuilder( 'Customize_Posts_Plugin' )
			->setMethods( array( 'has_required_core_version' ) )
			->getMock();

		$stub->expects( $this->any() )
			->method( 'has_required_core_version' )
			->with( $this->equalTo( false ) );

		$this->assertEquals( 10, has_action( 'admin_notices', array( $stub, 'show_core_version_dependency_failure' ) ) );
	}

	/**
	 * Test that the required version is met.
	 *
	 * @see Customize_Posts_Plugin::has_required_core_version()
	 */
	public function test_has_required_core_version() {
		$this->assertTrue( $this->plugin->has_required_core_version() );
	}

	/**
	 * Test register_customize_draft method.
	 *
	 * @see Customize_Posts_Plugin::register_customize_draft()
	 */
	public function test_register_customize_draft() {
		$this->plugin->register_customize_draft();
		global $wp_post_statuses;
		$this->assertArrayHasKey( 'customize-draft', $wp_post_statuses );
	}

	/**
	 * Test add_admin_bar_customize_link_queried_object_autofocus method.
	 *
	 * @covers Customize_Posts_Plugin::add_admin_bar_customize_link_queried_object_autofocus()
	 */
	public function test_add_admin_bar_customize_link_queried_object_autofocus() {
		global $wp_admin_bar;
		set_current_screen( 'front' );

		$post_id = $this->factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		remove_all_actions( 'admin_bar_menu' );

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$wp_admin_bar = new WP_Admin_Bar(); // WPCS: Override OK.
		$wp_admin_bar->initialize();
		$wp_admin_bar->add_menus();
		do_action_ref_array( 'admin_bar_menu', array( &$wp_admin_bar ) );
		$this->assertTrue( $this->plugin->add_admin_bar_customize_link_queried_object_autofocus( $wp_admin_bar ) );
		$node = $wp_admin_bar->get_node( 'customize' );
		$this->assertTrue( is_object( $node ) );
		$parsed_url = wp_parse_url( $node->href );
		$query_params = array();
		parse_str( $parsed_url['query'], $query_params );
		$this->assertArrayHasKey( 'autofocus', $query_params );
		$this->assertArrayHasKey( 'section', $query_params['autofocus'] );
		$this->assertEquals( sprintf( 'post[%s][%d]', get_post_type( $post_id ), $post_id ), $query_params['autofocus']['section'] );
	}

	/**
	 * Test that the user caps are modified.
	 *
	 * @see Customize_Posts_Plugin::grant_customize_capability()
	 */
	public function test_grant_customize_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertTrue( current_user_can( 'customize' ) );
	}

	/**
	 * Test that the manager is bootstrapped.
	 *
	 * @see Customize_Posts_Plugin::filter_customize_loaded_components()
	 */
	public function test_filter_customize_loaded_components() {
		$this->assertInstanceOf( 'WP_Customize_Posts', $this->wp_customize->posts );
	}

	/**
	 * Test that the error has the correct markup.
	 *
	 * @see Customize_Posts_Plugin::show_core_version_dependency_failure()
	 */
	public function test_show_core_version_dependency_failure() {
		ob_start();
		$this->plugin->show_core_version_dependency_failure();
		$markup = ob_get_contents();
		ob_end_clean();
		preg_match( '/<div class="error">\s+<p>(.*)<\/p>\s+<\/div>/i', $markup, $matches );
		$this->assertContains( '<div class="error">', $matches[0] );
		$this->assertNotEmpty( $matches[1] );
	}

	/**
	 * Test that scripts are registered.
	 *
	 * @see Customize_Posts_Plugin::register_scripts()
	 */
	function test_register_scripts() {
		$this->plugin->register_scripts( wp_scripts() );
		$this->assertTrue( wp_script_is( 'customize-posts-panel', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-post-section', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-dynamic-control', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-posts', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-post-field-partial', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-preview-posts', 'registered' ) );
		$this->assertTrue( wp_script_is( 'edit-post-preview-admin', 'registered' ) );
		$this->assertTrue( wp_script_is( 'edit-post-preview-customize', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-page-template', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-post-date-control', 'registered' ) );
		$this->assertTrue( wp_script_is( 'customize-post-status-control', 'registered' ) );
	}

	/**
	 * Test that styles are registered.
	 *
	 * @see Customize_Posts_Plugin::register_styles()
	 */
	function test_register_styles() {
		$this->plugin->register_styles( wp_styles() );
		$this->assertTrue( wp_style_is( 'customize-posts', 'registered' ) );
		$this->assertTrue( wp_style_is( 'edit-post-preview-customize', 'registered' ) );
	}

	/**
	 * Test on delete changeset all auto-draft posts created with it is deleted.
	 *
	 * @see Customize_Posts_Plugin::cleanup_autodraft_on_changeset_delete()
	 */
	function test_cleanup_autodraft_on_changeset_delete() {
		$this->assertEquals( 10, has_action( 'delete_post', array(
			$this->plugin,
			'cleanup_autodraft_on_changeset_delete',
		) ) );
		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$wp_customize = $GLOBALS['wp_customize'];
		if ( isset( $wp_customize->posts ) ) {
			$posts = $wp_customize->posts;
		}
		$auto_draft_post = $posts->insert_auto_draft_post( 'post' );
		$auto_draft_post_id = $auto_draft_post->ID;
		$post_setting_id = WP_Customize_Post_Setting::get_post_setting_id( $auto_draft_post );
		unset( $auto_draft_post );
		$data = array();
		$data[ $post_setting_id ] = array(
			'value' => array(
				'post_title' => 'Testing Post Publish',
				'post_status' => 'publish',
			),
		);
		$changeset_post_id = wp_insert_post( wp_slash( array(
			'post_type' => 'customize_changeset',
			'post_status' => 'auto-draft',
			'post_content' => wp_json_encode( $data ),
		) ) );
		// Duplicate post.
		$changeset_post_id_duplicate = wp_insert_post( wp_slash( array(
			'post_type' => 'customize_changeset',
			'post_status' => 'auto-draft',
			'post_content' => wp_json_encode( $data ),
		) ) );
		$this->assertInstanceOf( 'WP_Post', get_post( $auto_draft_post_id ) );
		wp_delete_post( $changeset_post_id, true );
		// Should not delete as duplicate post exists.
		$this->assertInstanceOf( 'WP_Post', get_post( $auto_draft_post_id ) );
		wp_delete_post( $changeset_post_id_duplicate, true );
		// Should delete as there are no other ref of setting draft post.
		$this->assertNull( get_post( $auto_draft_post_id ) );
		$this->wp_customize = null;
	}
}
