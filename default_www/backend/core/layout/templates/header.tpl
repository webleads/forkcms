<div id="headerHolder">
	<h1><a href="/" title="{$lblVisitWebsite|ucfirst}">{$SITE_TITLE}</a></h1>
	<table cellspacing="0" cellpadding="0" id="header">
		<tr>
			<td id="navigation">
				{$var|getmainnavigation}
				{* @todo move settings into mainnavigation *}
				<li style="list-style: none;">
					<a href="{$var|geturl:'index':'settings'}" class="icon iconSettings">
						{$lblSettings|ucfirst}
					</a>
				</li>
			</td>
			<td id="user">
				<ul>
					{option:debug}
						<li>
							<div id="debugnotify">{$lblDebugMode|ucfirst}</div>
						</li>
					{/option:debug}

					{option:workingLanguages}
						<li>
							{$msgNowEditing}:
							<select id="workingLanguage">
								{iteration:workingLanguages}
									<option{option:workingLanguages.selected} selected="selected"{/option:workingLanguages.selected} value="{$workingLanguages.abbr}">{$workingLanguages.label|ucfirst}</option>
								{/iteration:workingLanguages}
							</select>
						</li>
					{/option:workingLanguages}

					<li id="account">
						<a href="#ddAccount" id="openAccountDropdown" class="fakeDropdown">
							<div class="avatar av24">
								<img src="{$FRONTEND_FILES_URL}/backend_users/avatars/32x32/{$authenticatedUserAvatar}" width="24" height="24" alt="{$authenticatedUserNickname}" />
							</div>
							<span class="nickname">{$authenticatedUserNickname}</span>
							<span class="arrow">&#x25BC;</span>
						</a>
						<ul class="hidden" id="ddAccount">
							<li><a href="{$authenticatedUserEditUrl}">{$lblEditProfile|ucfirst}</a></li>
							<li><a target="_blank" href="http://userguide.fork-cms.be">{$lblUserguide|ucfirst}</a></li>
							<li><a target="_blank" href="https://github.com/forkcms/forkcms/wikis">{$lblDeveloper|ucfirst}</a></li>
							<li><a href="{$var|geturl:'logout':'authentication'}">{$lblSignOut|ucfirst}</a></li>
						</ul>
					</li>
				</ul>
			</td>
		</tr>
	</table>
</div>