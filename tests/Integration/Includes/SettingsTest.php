<?php

namespace LibreSign\WooNextcloud\Tests\Integration\Includes;

use LibreSign\WooNextcloud\Tests\Support\FakeHttp;
use LibreSign\WooNextcloud\Tests\Support\NextcloudSettings;
use WP_Error;
use WP_UnitTestCase;

final class SettingsTest extends WP_UnitTestCase {

	use NextcloudSettings;

	private const ENDPOINT = self::NEXTCLOUD_HOST . '/ocs/v2.php/cloud/user?format=json';

	private $nextcloud;

	public function set_up() {
		parent::set_up();

		$this->register_nextcloud_settings();

		$this->nextcloud = new FakeHttp();
	}

	private function ocs_body( $status, $statuscode, $id = '' ) {
		return wp_json_encode(
			array(
				'ocs' => array(
					'meta' => array(
						'status'     => $status,
						'statuscode' => $statuscode,
					),
					'data' => array( 'id' => $id ),
				),
			)
		);
	}

	public function test_asks_nextcloud_who_the_configured_user_is() {
		$this->nextcloud->answer_with( FakeHttp::response( 200, $this->ocs_body( 'ok', 200, 'admin' ) ) );

		agm_test_nextcloud_connection();

		$request = $this->nextcloud->requests()[0];

		$this->assertSame( self::ENDPOINT, $request['url'] );
		$this->assertSame( $this->expected_authorization(), $request['args']['headers']['Authorization'] );
		$this->assertSame( 15, $request['args']['timeout'] );
	}

	public function test_confirms_the_connection_naming_the_user_that_answered() {
		$this->nextcloud->answer_with( FakeHttp::response( 200, $this->ocs_body( 'ok', 200, 'admin' ) ) );

		$this->assertSame(
			array(
				'type'    => 'success',
				'message' => 'Conexão com o Nextcloud validada com sucesso usando o usuário admin.',
			),
			agm_test_nextcloud_connection()
		);
	}

	public function test_confirms_the_connection_when_nextcloud_names_no_user() {
		$this->nextcloud->answer_with( FakeHttp::response( 200, $this->ocs_body( 'ok', 200 ) ) );

		$this->assertSame(
			'Conexão com o Nextcloud validada com sucesso.',
			agm_test_nextcloud_connection()['message']
		);
	}

	public function test_refuses_to_test_before_the_credentials_are_filled_in() {
		delete_option( 'nextcloud_api_password' );

		$this->assertSame(
			array(
				'type'    => 'error',
				'message' => 'Preencha host, login e senha para testar a conexão com o Nextcloud.',
			),
			agm_test_nextcloud_connection()
		);
		$this->assertSame( array(), $this->nextcloud->urls() );
	}

	public function test_reports_a_host_that_cannot_be_reached() {
		$this->nextcloud->answer_with( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		$this->assertSame(
			array(
				'type'    => 'error',
				'message' => 'Falha ao conectar no Nextcloud: Connection timed out',
			),
			agm_test_nextcloud_connection()
		);
	}

	public function test_reports_credentials_nextcloud_rejected() {
		$this->nextcloud->answer_with( FakeHttp::response( 401, $this->ocs_body( 'failure', 997 ) ) );

		$this->assertSame(
			array(
				'type'    => 'error',
				'message' => 'Nextcloud respondeu com HTTP 401. Verifique host e credenciais.',
			),
			agm_test_nextcloud_connection()
		);
	}

	public function test_reports_an_ocs_answer_that_is_not_a_success() {
		$this->nextcloud->answer_with( FakeHttp::response( 200, $this->ocs_body( 'failure', 997 ) ) );

		$this->assertSame(
			array(
				'type'    => 'error',
				'message' => 'A API OCS respondeu com status inesperado (997). Verifique credenciais e permissões do usuário informado.',
			),
			agm_test_nextcloud_connection()
		);
	}

	public function test_builds_the_ocs_url_without_doubling_the_slash() {
		update_option( 'nextcloud_api_host', self::NEXTCLOUD_HOST . '/' );

		$this->assertSame(
			self::NEXTCLOUD_HOST . '/ocs/v2.php/cloud/user',
			agm_build_nextcloud_ocs_url( '/ocs/v2.php/cloud/user' )
		);
	}

	public function test_puts_the_settings_link_first_among_the_plugin_actions() {
		$this->assertSame(
			array(
				'<a href="options-general.php?page=nextcloud-config">Configurações</a>',
				'deactivate',
			),
			agm_add_settings_link( array( 'deactivate' ) )
		);
	}

	public function test_reports_the_result_on_the_settings_page_after_a_save() {
		set_current_screen( 'options-general' );
		$_GET['page']             = 'nextcloud-config';
		$_GET['settings-updated'] = 'true';

		$this->nextcloud->answer_with( FakeHttp::response( 200, $this->ocs_body( 'ok', 200, 'admin' ) ) );

		agm_maybe_test_nextcloud_connection_after_save();

		$errors = get_settings_errors( 'nextcloud_api_connection' );

		$this->assertSame( 'success', $errors[0]['type'] );
		$this->assertSame( 'Conexão com o Nextcloud validada com sucesso usando o usuário admin.', $errors[0]['message'] );
	}

	public function test_stays_quiet_on_a_page_that_is_not_the_settings_page() {
		set_current_screen( 'options-general' );
		$_GET['page']             = 'another-page';
		$_GET['settings-updated'] = 'true';

		agm_maybe_test_nextcloud_connection_after_save();

		$this->assertSame( array(), $this->nextcloud->urls() );
	}
}
