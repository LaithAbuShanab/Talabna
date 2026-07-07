<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="__('auth.profile.title')"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile')" icon="cog" wire:navigate>
                {{ __('auth.profile.title') }}
            </flux:menu.item>

            <flux:menu.item :href="route('logout')" icon="arrow-right-start-on-rectangle" data-test="logout-button" wire:navigate>
                {{ __('auth.profile.log_out') }}
            </flux:menu.item>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
