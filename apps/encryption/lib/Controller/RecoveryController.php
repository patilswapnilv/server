<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\Encryption\Controller;

use OCA\Encryption\Recovery;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class RecoveryController extends Controller {
	/**
	 * @param string $AppName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IL10N $l
	 * @param Recovery $recovery
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		private IConfig $config,
		private IL10N $l,
		private Recovery $recovery,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @param string $recoveryPassword
	 * @param string $confirmPassword
	 * @param string $adminEnableRecovery
	 * @return DataResponse
	 */
	public function adminRecovery($recoveryPassword, $confirmPassword, $adminEnableRecovery) {
		// Check if both passwords are the same
		if (empty($recoveryPassword)) {
			$errorMessage = $this->l->t('Missing recovery key password');
			return new DataResponse(['data' => ['message' => $errorMessage]],
				Http::STATUS_BAD_REQUEST);
		}

		if (empty($confirmPassword)) {
			$errorMessage = $this->l->t('Please repeat the recovery key password');
			return new DataResponse(['data' => ['message' => $errorMessage]],
				Http::STATUS_BAD_REQUEST);
		}

		if ($recoveryPassword !== $confirmPassword) {
			$errorMessage = $this->l->t('Repeated recovery key password does not match the provided recovery key password');
			return new DataResponse(['data' => ['message' => $errorMessage]],
				Http::STATUS_BAD_REQUEST);
		}

		if (isset($adminEnableRecovery) && $adminEnableRecovery === '1') {
			if ($this->recovery->enableAdminRecovery($recoveryPassword)) {
				return new DataResponse(['data' => ['message' => $this->l->t('Recovery key successfully enabled')]]);
			}
			return new DataResponse(['data' => ['message' => $this->l->t('Could not enable recovery key. Please check your recovery key password!')]], Http::STATUS_BAD_REQUEST);
		} elseif (isset($adminEnableRecovery) && $adminEnableRecovery === '0') {
			if ($this->recovery->disableAdminRecovery($recoveryPassword)) {
				return new DataResponse(['data' => ['message' => $this->l->t('Recovery key successfully disabled')]]);
			}
			return new DataResponse(['data' => ['message' => $this->l->t('Could not disable recovery key. Please check your recovery key password!')]], Http::STATUS_BAD_REQUEST);
		}
		// this response should never be sent but just in case.
		return new DataResponse(['data' => ['message' => $this->l->t('Missing parameters')]], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @param string $newPassword
	 * @param string $oldPassword
	 * @param string $confirmPassword
	 * @return DataResponse
	 */
	public function changeRecoveryPassword($newPassword, $oldPassword, $confirmPassword) {
		//check if both passwords are the same
		if (empty($oldPassword)) {
			$errorMessage = $this->l->t('Please provide the old recovery password');
			return new DataResponse(['data' => ['message' => $errorMessage]], Http::STATUS_BAD_REQUEST);
		}

		if (empty($newPassword)) {
			$errorMessage = $this->l->t('Please provide a new recovery password');
			return new DataResponse(['data' => ['message' => $errorMessage]], Http::STATUS_BAD_REQUEST);
		}

		if (empty($confirmPassword)) {
			$errorMessage = $this->l->t('Please repeat the new recovery password');
			return new DataResponse(['data' => ['message' => $errorMessage]], Http::STATUS_BAD_REQUEST);
		}

		if ($newPassword !== $confirmPassword) {
			$errorMessage = $this->l->t('Repeated recovery key password does not match the provided recovery key password');
			return new DataResponse(['data' => ['message' => $errorMessage]], Http::STATUS_BAD_REQUEST);
		}

		$result = $this->recovery->changeRecoveryKeyPassword($newPassword,
			$oldPassword);

		if ($result) {
			return new DataResponse(
				[
					'data' => [
						'message' => $this->l->t('Password successfully changed.')]
				]
			);
		}
		return new DataResponse(
			[
				'data' => [
					'message' => $this->l->t('Could not change the password. Maybe the old password was not correct.')
				]
			], Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @param string $userEnableRecovery
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function userSetRecovery($userEnableRecovery) {
		if ($userEnableRecovery === '0' || $userEnableRecovery === '1') {
			$result = $this->recovery->setRecoveryForUser($userEnableRecovery);

			if ($result) {
				if ($userEnableRecovery === '0') {
					return new DataResponse(
						[
							'data' => [
								'message' => $this->l->t('Recovery Key disabled')]
						]
					);
				}
				return new DataResponse(
					[
						'data' => [
							'message' => $this->l->t('Recovery Key enabled')]
					]
				);
			}
		}
		return new DataResponse(
			[
				'data' => [
					'message' => $this->l->t('Could not enable the recovery key, please try again or contact your administrator')
				]
			], Http::STATUS_BAD_REQUEST);
	}
}
