<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<li :class="{'sharing-entry--share': share}" class="sharing-entry">
		<Avatar :isNoUser="true" class="sharing-entry__avatar icon-public-white" />
		<div class="sharing-entry__desc">
			<h4>{{ title }}</h4>
		</div>

		<!-- clipboard -->
		<Actions ref="copyButton" v-if="share && share.token"
			:disable-tooltip="true" class="sharing-entry__copy"
			v-tooltip.auto="{
				// make sure to manually show the tooltip aagain after click
				// as it will take away the focus and close the tooltip
				show: copied,
				content: clipboardTooltip,
				trigger: copied ? 'manual' : 'hover'
			}">
			<ActionLink :href="shareLink" target="_blank" icon="icon-clippy" @click.stop.prevent="copyLink">{{ shareLink }}</ActionLink>
		</Actions>

		<!-- pensing actions -->
		<Actions v-if="!loading && (pendingPassword || pendingExpirationDate)"
			class="sharing-entry__actions" menu-align="right"
			:open.sync="open" @close="onNewLinkShare">

			<!-- pending data menu -->
			<ActionText v-if="errors.pending"
				icon="icon-error" :class="{ error: errors.pending}">
				{{ errors.pending }}
			</ActionText>
			<ActionText v-else icon="icon-info">
				{{ t('files_sharing', 'Please enter the following required information before creating the share') }}
			</ActionText>

			<!-- password -->
			<ActionText v-if="pendingPassword" icon="icon-password">
				{{ t('files_sharing', 'Password protection enforced') }}
			</ActionText>
			<ActionInput  v-if="pendingPassword"
				:value.sync="share.password"
				:disabled="saving"
				icon=""
				autocomplete="new-password"
				@submit="onNewLinkShare">
				{{ t('files_sharing', 'Enter a password') }}
			</ActionInput>

			<!-- expiration date -->
			<ActionText v-if="pendingExpirationDate" icon="icon-calendar-dark">
				{{ t('files_sharing', 'Select an expiration date') }}
			</ActionText>
			<ActionInput v-if="pendingExpirationDate"
				:disabled="saving"
				:first-day-of-week="firstDay"
				:lang="lang"
				:value="share.expireDate"
				icon=""
				type="date"
				:not-before="dateTomorrow"
				:not-after="dateMaxEnforced"
				@update:value="onNewLinkShare">
				{{ t('files_sharing', 'Enter a date') }}
			</ActionInput>

			<ActionButton icon="icon-close" @click.prevent.stop="onCancel">
				{{ t('files_sharing', 'Cancel') }}
			</ActionButton>
		</Actions>

		<!-- actions -->
		<Actions v-else-if="!loading" class="sharing-entry__actions"
			menu-align="right" :open.sync="open"
			@close="onMenuClose">
				<template v-if="share">
					<!-- folder -->
					<template v-if="isFolder && fileHasCreatePermission && this.config.isPublicUploadEnabled">
						<ActionCheckbox :checked="share.permissions === publicUploadRValue"
							:value="publicUploadRValue"
							:disabled="saving"
							@change="togglePermissions">{{ t('files_sharing', 'Read only') }}</ActionCheckbox>
						<ActionCheckbox :checked="share.permissions === publicUploadRWValue"
							:value="publicUploadRWValue"
							:disabled="saving"
							@change="togglePermissions">{{ t('files_sharing', 'Allow upload and editing') }}</ActionCheckbox>
						<ActionCheckbox :checked="share.permissions === publicUploadWValue"
							:value="publicUploadWValue"
							:disabled="saving"
							class="sharing-entry__action--public-upload"
							@change="togglePermissions">{{ t('files_sharing', 'File drop (upload only)') }}</ActionCheckbox>
					</template>

					<!-- file -->
					<ActionCheckbox v-else
						:checked.sync="canUpdate"
						:disabled="saving"
						@change="queueUpdate('permissions')">
						{{ t('files_sharing', 'Allow editing') }}
					</ActionCheckbox>

					<ActionCheckbox 
						:checked.sync="share.hideDownload"
						:disabled="saving"
						@change="queueUpdate('hideDownload')">
						{{ t('files_sharing', 'Hide download') }}
					</ActionCheckbox>

					<!-- password -->
					<ActionCheckbox :checked.sync="isPasswordProtected"
						:disabled="config.enforcePasswordForPublicLink || saving"
						@uncheck="queueUpdate('password')">
						{{ config.enforcePasswordForPublicLink 
							? t('files_sharing', 'Password protection enforced')
							: t('files_sharing', 'Password protect') }}
					</ActionCheckbox>
					<ActionInput v-if="isPasswordProtected"
						:class="{ error: errors.password}"
						:disabled="saving"
						:value="hasUnsavedPassword ? share.newPassword : '***************'"
						icon="icon-password"
						ref="password"
						autocomplete="new-password"
						v-tooltip.auto="{
							content: errors.password,
							show: errors.password,
							trigger: 'manual'
						}"
						:type="hasUnsavedPassword ? 'text': 'password'"
						@update:value="onPasswordChange"
						@submit="debounceQueueUpdate('password')">
						{{ t('files_sharing', 'Enter a password') }}
					</ActionInput>

					<!-- expiration date -->
					<ActionCheckbox :checked.sync="hasExpirationDate"
						:disabled="config.isDefaultExpireDateEnforced || saving"
						@uncheck="queueUpdate('expireDate')">
						{{ config.isDefaultExpireDateEnforced 
							? t('files_sharing', 'Expiration date enforced')
							: t('files_sharing', 'Set expiration date') }}
					</ActionCheckbox>
					<ActionInput v-if="hasExpirationDate"
						:class="{ error: errors.expireDate}"
						:disabled="saving"
						:first-day-of-week="firstDay"
						:lang="lang"
						:value="share.expireDate"
						icon="icon-calendar-dark"
						ref="expireDate"
						type="date"
						v-tooltip.auto="{
							content: errors.expireDate,
							show: errors.expireDate,
							trigger: 'manual'
						}"
						:not-before="dateTomorrow"
						:not-after="dateMaxEnforced"
						@update:value="onExpirationChange">
						{{ t('files_sharing', 'Enter a date') }}
					</ActionInput>
					
					<!-- note -->
					<ActionCheckbox :checked.sync="hasNote"
						:disabled="saving"
						@uncheck="queueUpdate('note')">
						{{ t('files_sharing', 'Note to recipient') }}
					</ActionCheckbox>
					<ActionTextEditable v-if="hasNote"
						:class="{ error: errors.note}"
						:disabled="saving"
						:value.sync="share.note"
						icon="icon-edit"
						ref="note"
						v-tooltip.auto="{
							content: errors.note,
							show: errors.note,
							trigger: 'manual'
						}"
						@update:value="debounceQueueUpdate('note')" />

					<ActionButton icon="icon-delete" :disabled="saving" @click.prevent="onDelete">
						{{ t('files_sharing', 'Delete share link') }}
					</ActionButton>
					<ActionButton class="new-share-link" icon="icon-add" @click.prevent.stop="onNewLinkShare">
						{{ t('files_sharing', 'Add another link') }}
					</ActionButton>
				</template>

				<!-- Create new share -->
				<ActionButton v-else class="new-share-link" icon="icon-add" @click.prevent.stop="onNewLinkShare">
					{{ t('files_sharing', 'Create a new share link') }}
				</ActionButton>
		</Actions>

		<!-- loading indicator to replace the menu -->
		<div v-else class="icon-loading-small sharing-entry__loading"></div>
	</li>
</template>

<script>
import { generateOcsUrl, generateUrl } from 'nextcloud-router/dist/index'
import axios from 'nextcloud-axios'
import PQueue from 'p-queue'
import debounce from 'debounce'

import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import ActionCheckbox from 'nextcloud-vue/dist/Components/ActionCheckbox'
import ActionInput from 'nextcloud-vue/dist/Components/ActionInput'
import ActionText from 'nextcloud-vue/dist/Components/ActionText'
import ActionTextEditable from 'nextcloud-vue/dist/Components/ActionTextEditable'
import ActionLink from 'nextcloud-vue/dist/Components/ActionLink'
import Actions from 'nextcloud-vue/dist/Components/Actions'
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import Tooltip from 'nextcloud-vue/dist/Directives/Tooltip'

import Config from '../services/ConfigService'
import Share from '../models/Share';
import SharesMixin from '../mixins/SharesMixin'

const updateQueue = new PQueue({ concurrency: 1 })

export default {
	name: 'SharingEntryLink',

	components: {
		Actions,
		ActionButton,
		ActionCheckbox,
		ActionInput,
		ActionLink,
		ActionText,
		ActionTextEditable,
		Avatar
	},

	mixins: [SharesMixin],

	directives: {
		Tooltip
	},

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true
		},
		share: {
			type: Share,
			default: null
		}
	},

	data() {
		return {
			config: new Config(),
			copySuccess: true,
			copied: false,

			errors: {},
			errorTimeout: null,

			loading: false,
			saving: false,
			open: false,
			/**
			 * ! This allow vue to make the Share class state reactive
			 * ! do not remove it ot you'll lose all reactivity here
			 */
			reactiveState: this.share && this.share.state,

			publicUploadRWValue: OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE | OC.PERMISSION_READ | OC.PERMISSION_DELETE,
			publicUploadRValue: OC.PERMISSION_READ,
			publicUploadWValue: OC.PERMISSION_CREATE,
		}
	},

	computed: {

		/**
		 * Link share label
		 * TODO: allow editing
		 */
		title() {
			if (this.share && this.share.label && this.share.label.trim() !== '') {
				return this.share.label
			}
			return t('files_sharing', 'Share link')
		},

		/**
		 * Pending data.
		 * If the share still doesn't have an id, it is not synced
		 * Therefore this is still not valid
		 */
		pendingPassword() {
			return this.config.enforcePasswordForPublicLink && this.share && !this.share.id
		},
		pendingExpirationDate() {
			return this.config.isDefaultExpireDateEnforced && this.share && !this.share.id
		},

		/**
		 * Can the recipient edit the file ?
		 * @returns {boolean}
		 */
		canUpdate: {
			get: function() {
				return this.share.hasUpdatePermission
			},
			set: function(enabled) {
				this.share.permissions = enabled
					? OC.PERMISSION_READ | OC.PERMISSION_UPDATE
					: OC.PERMISSION_READ
			}
		},

		/**
		 * Is the current share password protected ?
		 * @returns {boolean}
		 */
		isPasswordProtected: {
			get: function() {
				return this.config.enforcePasswordForPublicLink || !!this.share.password
			},
			set: function(enabled) {
				// TODO: directly save after generation to make sure the share is always protected
				this.share.password = enabled ? this.generatePassword() : ''
			}
		},

		/**
		 * Does the current share have an expiration date
		 * @returns {boolean}
		 */
		hasExpirationDate: {
			get: function() {
				return this.config.isDefaultExpireDateEnforced || !!this.share.expireDate
			},
			set: function(enabled) {
				this.share.expireDate = enabled
					? this.config.defaultExpirationDateString !== ''
						? this.config.defaultExpirationDateString
						: moment().format('YYYY-MM-DD')
					: ''
			}
		},

		/**
		 * Does the current share have a note
		 * @returns {boolean}
		 */
		hasNote: {
			get: function() {
				return !!this.share.note
			},
			set: function(enabled) {
				this.share.note = enabled
					? t('files_sharing', 'Enter a note for the share recipient')
					: ''
			}
		},

		hasUnsavedPassword() {
			return this.share.newPassword && this.share.newPassword !== ''
		},

		/**
		 * Is the current share a folder ?
		 * TODO: move to a proper FileInfo model?
		 * @returns {boolean}
		 */
		isFolder() {
			return this.fileInfo.type === 'dir'
		},

		/**
		 * Does the current file/folder have create permissions
		 * TODO: move to a proper FileInfo model?
		 * @returns {boolean}
		 */
		fileHasCreatePermission()  {
			return this.fileInfo.permissions & OC.PERMISSION_CREATE ? true : false
		},

		/** 
		 * Return the public share link
		 * @returns {string}
		 */
		shareLink() {
			return window.location.protocol + '//' + window.location.host + generateUrl('/s/') + this.share.token
		},

		/**
		 * Clipboard v-tooltip message 
		 * @returns {string}
		 */
		clipboardTooltip() {
			if (this.copied) {
				return this.copySuccess
					? t('files_sharing', 'Link copied')
					: t('files_sharing', 'Cannot copy, please copy the link manually')
			}
			return t('files_sharing', 'Copy to clipboard')
		},

		dateTomorrow() {
			return moment().add(1, 'days')
		},

		dateMaxEnforced() {
			return this.config.isDefaultExpireDateEnforced && moment().add(1 + this.config.defaultExpireDate, 'days')
		}
	},

	methods: {
		/**
		 * Create a new share link and append it to the list
		 */
		async onNewLinkShare() {
			const shareDefaults = {}
			if (this.config.isDefaultExpireDateEnforced) {
				// default is empty string if not set
				shareDefaults.expireDate = this.config.defaultExpirationDateString
			}

			// do not push yet if we need a password or an expiration date
			if (this.config.enforcePasswordForPublicLink || this.config.isDefaultExpireDateEnforced) {
				// if a share already exists, pushing it
				if (this.share && !this.share.id) {
					if (this.checkShare(this.share)) {
						await this.pushNewLinkShare(this.share)
						return true
					} else {
						this.open = true
						OC.Notification.showTemporary(t('files_sharing', 'Error, please enter proper password and/or expiration date'))
						return false
					}
				}

				console.info(shareDefaults);

				// ELSE, show the pending popovermenu
				// if password enforced, pre-fill with random one
				if (this.config.enforcePasswordForPublicLink) {
					shareDefaults.password = this.generatePassword()
				}

				// create share & close menu
				const share = new Share(shareDefaults)
				const component = await new Promise(resolve => {
					this.$emit('add:share', share, resolve)
				})

				// open the menu on the
				// freshly created share component
				this.open = false
				component.open = true
			
			// Nothing enforced, creating share directly
			} else {
				const share = new Share(shareDefaults)
				await this.pushNewLinkShare(share)
			}
		},

		updated() {
			console.info('updated');
		},

		async pushNewLinkShare(share) {
			try {
				this.loading = true
				this.open = false

				const path = this.fileInfo.path + this.fileInfo.name
				const newShare = await this.createShare({
					path,
					shareType: OC.Share.SHARE_TYPE_LINK,
					password: share.password,
					expireDate: share.expireDate
					// we do not allow setting the publicUpload
					// before the share creation.
					// Todo: We also need to fix the createShare method in 
					// lib/Controller/ShareAPIController.php to allow file drop
					// (currently not supported on create, only update)
				})
				
				console.debug('Link share created', newShare)

				// if share already exists, copy link directly on next tick
				let component
				if (share) {
					component = await new Promise(resolve => {
						this.$emit('update:share', newShare, resolve)
					})
				}
				// adding new share to the array and copying link to clipboard
				else {
					// using promise so that we can copy link in the same click function
					// and avoid firefox copy permissions issue
					component = await new Promise(resolve => {
						this.$emit('add:share', newShare, resolve)
					})
				}

				// Execute the copy link method
				// freshly created share component
				// ! somehow does not works on firefox !
				component.copyLink()

			} catch({ response }) {
				const message = response.data.ocs.meta.message
				this.onSyncError('pending', message)
			} finally {
				this.loading = false
			}
		},

		async onDelete() {
			try {
				this.loading = true
				this.open = false
				await this.deleteShare(this.share.id)
				console.debug('Link share deleted', this.share.id);
				this.$emit('remove:share', this.share)
			} catch(error) {
				// re-open menu if error
				this.open = true
			} finally {
				this.loading = false
			}
		},

		openMenu() {
			this.open = true
		},

		togglePermissions(e) {
			const permissions = parseInt(e.target.value, 10)
			this.share.permissions = permissions
			this.queueUpdate('permissions')
		},

		generatePassword() {
			// ! TODO add default password / generate with password policy
			return 'password'
		},
		
		async copyLink() {
			try {
				await this.$copyText(this.shareLink)
				this.copySuccess = true
				this.copied = true
			} catch (error) {
				this.copySuccess = false
				this.copied = true
				console.error(error);
			} finally {
				setTimeout(() => {
					this.copySuccess = false
					this.copied = false
				}, 4000)
			}
		},

		/**
		 * ActionInput can be a little tricky to work with.
		 * Since we expect a string and not a Date,
		 * we need to process the value here
		 */
		onExpirationChange(date) {
			// format to YYYY-MM-DD
			const value = moment(date).format('YYYY-MM-DD')
			this.share.expireDate = value
			this.queueUpdate('expireDate')
		},

		/**
		 * Update password and newPassword values
		 * of share. If password is set but not newPassword
		 * then the user did not changed the password
		 * If both co-exists, the password have changed and
		 * we show it in plain text.
		 * Then on submit (or menu close), we sync it.
		 */
		onPasswordChange(password) {
			this.$set(this.share, 'newPassword', password)
			this.share.password = password
		},

		/**
		 * Menu have been closed.
		 * The only property that does not get
		 * synced automatically is the password
		 * So let's check if we have an unsaved
		 * password.
		 * expireDate is saved on datepicker pick
		 * or close.
		 */
		onMenuClose() {
			if (this.hasUnsavedPassword) {
				this.queueUpdate('password')
			}
		},

		/**
		 * Send an update of the share to the queue
		 *
		 * @param {string} property the property to sync
		 */
		queueUpdate(property) {
			const value = this.share[property]
			updateQueue.add(async () => {
				this.saving = true
				try {
					await this.updateShare(this.share.id, {
						property,
						value
					})

					// reset password state after sync
					if (property === 'password') {
						this.$delete(this.share, 'newPassword')
					}
					// clear any previous errors
					this.$delete(this.errors, property)
				} catch({ property, message }) {
					this.onSyncError(property, message)
				} finally {
					this.saving = false
				}
			})
		},

		/**
		 * Manage sync errors
		 * @param {string} property the errored property, e.g. 'password'
		 * @param {string} message the error message
		 */
		onSyncError(property, message) {
			// re-open menu if closed
			this.open = true
			switch (property) {
				case 'password':
				case 'pending':
				case 'expireDate':
				case 'note':
					// show error
					this.$set(this.errors, property, message)

					// Reset errors after  4 seconds
					clearTimeout(this.errorTimeout)
					this.errorTimeout = setTimeout(() => {
						this.errors = {}
					}, 4000)

					if (this.$refs[property]) {
						// focus if there is a focusable action element
						const focusable = this.$refs[property].querySelector('.focusable')
						if (focusable) {
							focusable.focus()
						}
					}
					break;
			}
		},

		/**
		 * Debounce queueUpdate to avoid requests spamming
		 * more importantly for text data
		 * 
		 * @param {string} property the property to sync
		 */
		debounceQueueUpdate: debounce(function(property) {
			this.queueUpdate(property)
		}, 500),

		/**
		 * Cancel the share creation
		 * Used in the pending popover
		 */
		onCancel() {
			//this.share already exists at this point,
			// but is incomplete as not pushed to server
			// YET. We can safely delete the share :)
			this.$emit('remove:share', this.share)
		}
	}

}
</script>
  
<style lang="scss" scoped>
.sharing-entry {
	display: flex;
	align-items: center;
	height: 44px;
	&__desc {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		padding: 8px;
		line-height: 1.2em;
	}

	&:not(.sharing-entry--share) &__actions {
		.new-share-link {
			border-top: 1px solid var(--color-border);
		}
	}

	.sharing-entry__action--public-upload {
		border-bottom: 1px solid var(--color-border);
	}

	&__loading {
		width: 44px;
		height: 44px;
		margin: 0;
		padding: 14px;
		margin-left: auto;
	}

	// put menus to the left
	// but only the first one
	.action-item {
		margin-left: auto;
		~ .action-item,
		~ .sharing-entry__loading {
			margin-left: 0;
		}
	}

	&::v-deep .action-item__menu {
		li.error {
			animation: error 1s ease-in-out;
			animation-iteration-count: 3;
		}
	}
}
@keyframes error {
	0% {
		background-color: var(--color-error)
	}
	50% {
		background-color: var(--color-main-background)
	}
	100% {
		background-color: var(--color-error)
	}
}
</style>
