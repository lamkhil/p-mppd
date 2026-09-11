<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BerkasTerbaruTable;
use App\Filament\Widgets\SipKedaluwarsaTable;
use App\Filament\Widgets\StatOverViewSuratIzinPraktik;
use App\Filament\Widgets\SuratIzinPraktikPie;
use App\Filament\Widgets\SuratIzinPraktikProfesiBar;
use App\Filament\Widgets\SuratIzinPraktikTempatPraktikBar;
use App\Filament\Widgets\SuratIzinPraktikTrend;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->brandName('P-MPPD')
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->font('Inter')
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->breadcrumbs(true)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                NavigationGroup::make('Perizinan')->icon('heroicon-o-document-text'),
                NavigationGroup::make('Laporan')->icon('heroicon-o-chart-bar'),
                NavigationGroup::make('Pengaturan')->icon('heroicon-o-cog-6-tooth')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                StatOverViewSuratIzinPraktik::class,
                SuratIzinPraktikTrend::class,
                SuratIzinPraktikPie::class,
                SuratIzinPraktikProfesiBar::class,
                SuratIzinPraktikTempatPraktikBar::class,
                SipKedaluwarsaTable::class,
                BerkasTerbaruTable::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                AuthUIEnhancerPlugin::make()
                    ->formPanelPosition('right')
                    ->emptyPanelBackgroundImageOpacity('70%')
                    ->showEmptyPanelOnMobile(false)
                    ->formPanelWidth('40%')
                    ->emptyPanelBackgroundColor(Color::Blue, '900')
                    ->emptyPanelBackgroundImageUrl('login.jpg'),
            ])
            ->databaseNotifications();
    }
}
