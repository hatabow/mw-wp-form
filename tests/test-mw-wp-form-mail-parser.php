<?php
class MW_WP_Form_Mail_Parser_Test extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	protected $form_key;

	/**
	 * @var MW_WP_Form_Mail
	 */
	protected $Mail;

	/**
	 * @var MW_WP_Form_Data
	 */
	protected $Data;

	/**
	 * @var MW_WP_Form_Setting
	 */
	protected $Setting;

	public function setUp() {
		parent::setUp();
		$post_id = $this->factory->post->create( array(
			'post_type' => MWF_Config::NAME,
		) );
		$this->form_key = MWF_Config::NAME . '-' . $post_id;
		$this->Mail    = new MW_WP_Form_Mail();
		$this->Setting = new MW_WP_Form_Setting( $post_id );
		$this->Data    = MW_WP_Form_Data::getInstance( MWF_Functions::get_form_key_from_form_id( $post_id ) );
	}

	/**
	 * @group get_parsed_mail_object
	 * @backupStaticAttributes enabled
	 */
	public function test_get_parsed_mail_object_ToとCCとBCCは上書きされない() {
		$this->Data->set( 'to'    , 'to@example.com' );
		$this->Data->set( 'cc'    , 'cc@example.com' );
		$this->Data->set( 'bcc'   , 'bcc@example.com' );
		$this->Data->set( 'from'  , 'from@example.com' );
		$this->Data->set( 'sender', 'Sender' );
		$this->Data->set( 'body'  , 'body' );
		$this->Mail->to     = '{to}';
		$this->Mail->cc     = '{cc}';
		$this->Mail->bcc    = '{bcc}';
		$this->Mail->from   = '{from}';
		$this->Mail->sender = '{sender}';
		$this->Mail->body   = '{body}';
		$Mail_Parser = new MW_WP_Form_Mail_Parser( $this->Mail, $this->Setting );
		$Mail = $Mail_Parser->get_parsed_mail_object( false );

		$this->assertEquals( '', $Mail->to );
		$this->assertEquals( '', $Mail->cc );
		$this->assertEquals( '', $Mail->bcc );
		$this->assertEquals( 'from@example.com', $Mail->from );
		$this->assertEquals( 'Sender', $Mail->sender );
		$this->assertEquals( 'body', $Mail->body );
	}

	/**
	 * @group get_parsed_mail_object
	 * @backupStaticAttributes enabled
	 */
	public function test_get_parsed_mail_object_データベースに保存() {
		$this->Data->set( 'example', 'example' );
		$this->Mail->body = '{example}';
		$Mail_Parser = new MW_WP_Form_Mail_Parser( $this->Mail, $this->Setting );
		$Mail_Parser->get_parsed_mail_object( true );

		$posts = get_posts( array(
			'post_type' => MWF_Functions::get_contact_data_post_type_from_form_id( $this->Setting->get( 'post_id' ) ),
		) );
		foreach ( $posts as $post ) {
			$this->assertEquals(
				'example',
				get_post_meta( $post->ID, 'example', true )
			);
			break;
		}
	}

	/**
	 * @group get_parsed_mail_object
	 * @backupStaticAttributes enabled
	 */
	public function test_get_parsed_mail_object_Nullでもデータベースに保存() {
		$this->Data->set( 'example', null );
		$this->Mail->body = '{example}';
		$Mail_Parser = new MW_WP_Form_Mail_Parser( $this->Mail, $this->Setting );
		$Mail_Parser->get_parsed_mail_object( true );

		$posts = get_posts( array(
			'post_type' => MWF_Functions::get_contact_data_post_type_from_form_id( $this->Setting->get( 'post_id' ) ),
		) );
		foreach ( $posts as $post ) {
			$post_metas = get_post_meta( $post->ID );
			$this->assertTrue( isset( $post_metas['example'] ) );
			break;
		}
	}

	/**
	 * @group get_parsed_mail_oabject
	 * @group tracking_number
	 * @backupStaticAttributes enabled
	 */
	public function test_get_parsed_mail_object_tracking_number() {
		$this->Mail->body = '{' . MWF_Config::TRACKINGNUMBER . '}';
		$Mail_Parser = new MW_WP_Form_Mail_Parser( $this->Mail, $this->Setting );

		$Mail = $Mail_Parser->get_parsed_mail_object( false );
		$this->assertEquals( 1, $Mail->body );
	}

	/**
	 * @group get_parsed_mail_object
	 * @group custom_mail_tag
	 * @backupStaticAttributes enabled
	 */
	public function test_get_parsed_mail_object_mwform_custom_mail_tag() {
		$self = $this;
		add_filter(
			'mwform_custom_mail_tag_' . $this->form_key,
			function( $value, $key, $insert_id ) use( $self ) {
				if ( $key === 'custom_tag' ) {
					return 'hoge';
				}
				return $value;
			},
			10,
			3
		);

		$this->Mail->body = '{custom_tag}';
		$Mail_Parser = new MW_WP_Form_Mail_Parser( $this->Mail, $this->Setting );
		$Mail = $Mail_Parser->get_parsed_mail_object( false );

		$this->assertEquals( 'hoge', $Mail->body );
	}

	/**
	 * @group get_parsed_mail_object
	 * @group custom_mail_tag
	 * @backupStaticAttributes enabled
	 */
	public function test_get_parsed_mail_object_mwform_custom_mail_tag_TO_CC_BCCも対応() {
		$self = $this;
		add_filter(
			'mwform_custom_mail_tag_' . $this->form_key,
			function( $value, $key, $insert_id ) use( $self ) {
				if ( $key === '_to' ) {
					return 'to@example.com';
				} elseif ( $key === '_cc' ) {
					return 'cc@example.com';
				} elseif ( $key === '_bcc' ) {
					return 'bcc@example.com';
				}
				return $value;
			},
			10,
			3
		);

		$this->Mail->to  = '{_to}';
		$this->Mail->cc  = '{_cc}';
		$this->Mail->bcc = '{_bcc}';
		$Mail_Parser = new MW_WP_Form_Mail_Parser( $this->Mail, $this->Setting );
		$Mail = $Mail_Parser->get_parsed_mail_object( false );

		$this->assertEquals( 'to@example.com', $Mail->to );
		$this->assertEquals( 'cc@example.com', $Mail->cc );
		$this->assertEquals( 'bcc@example.com', $Mail->bcc );
	}
}
