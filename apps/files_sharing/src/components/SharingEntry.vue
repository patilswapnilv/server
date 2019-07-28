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
	<li class="sharing-entry">
		<Avatar class="sharing-entry__avatar" :user="share.shareWith"
			:display-name="share.shareWithDisplayName" />
		<div class="sharing-entry__desc" v-tooltip.auto="tooltip">
			<h4>{{ title }}</h4>
		</div>
		<Actions menu-align="right" class="sharing-entry__actions">
			<slot />
		</Actions>
	</li>
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import Actions from 'nextcloud-vue/dist/Components/Actions'
import Tooltip from 'nextcloud-vue/dist/Directives/Tooltip'

import Share from '../models/Share'

const SHARE_TYPES = {
	SHARE_TYPE_USER: OC.Share.SHARE_TYPE_USER, 
	SHARE_TYPE_GROUP: OC.Share.SHARE_TYPE_GROUP, 
	SHARE_TYPE_LINK: OC.Share.SHARE_TYPE_LINK, 
	SHARE_TYPE_EMAIL: OC.Share.SHARE_TYPE_EMAIL, 
	SHARE_TYPE_REMOTE: OC.Share.SHARE_TYPE_REMOTE, 
	SHARE_TYPE_CIRCLE: OC.Share.SHARE_TYPE_CIRCLE, 
	SHARE_TYPE_GUEST: OC.Share.SHARE_TYPE_GUEST, 
	SHARE_TYPE_REMOTE_GROUP: OC.Share.SHARE_TYPE_REMOTE_GROUP,
	SHARE_TYPE_ROOM: OC.Share.SHARE_TYPE_ROOM
}

export default {
	name: 'SharingEntry',

	components: {
		Actions,
		Avatar
	},

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
			required: true,
			default: () => new Share({})
		}
	},

	data() {
		return {
			
		}
	},

	computed: {
		title() {
			let title = this.share.shareWithDisplayName
			if (this.share.type === SHARE_TYPES.SHARE_TYPE_GROUP) {
				title += ` (${ t('files_sharing', 'group') })`
			} else if (this.share.type === SHARE_TYPES.SHARE_TYPE_ROOM) {
				title += ` (${ t('files_sharing', 'conversation') })`
			}
			return title
		},
		tooltip() {
			if (this.share.owner !== this.share.uidFileOwner) {
				const data = {
					// todo: strong or italic?
					// but the t function escape any html from the data :/
					user: this.share.shareWithDisplayName,
					owner: this.share.owner
				}
				
				if (this.share.type === SHARE_TYPES.SHARE_TYPE_GROUP) {
					return t('files_sharing', 'Shared with the group {user} by {owner}', data)
				} else if (this.share.type === SHARE_TYPES.SHARE_TYPE_ROOM) {
					return t('files_sharing', 'Shared with the conversation {user} by {owner}', data)
				}

				return t('files_sharing', 'Shared with {user} by {owner}', data)
			}
		}
	},

	methods: {
		
	},

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
		p {
			color: var(--color-text-maxcontrast);
		}
	}
	&__actions {
		margin-left: auto;
	}
}
</style>
