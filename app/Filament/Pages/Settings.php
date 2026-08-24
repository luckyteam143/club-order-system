<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\OrderResource;
use App\Models\Setting;
use App\Support\MailConfigurator;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public const TIMEZONES = [
        'America/St_Johns'  => 'Newfoundland Time',
        'America/Halifax'   => 'Atlantic Time',
        'America/Toronto'   => 'Eastern Time (Canada default)',
        'America/Winnipeg'  => 'Central Time',
        'America/Edmonton'  => 'Mountain Time',
        'America/Vancouver' => 'Pacific Time',
    ];

    public function mount(): void
    {
        $retentionByModule = json_decode(Setting::get('log_retention_by_module', '{}'), true) ?: [];

        $this->form->fill([
            'app_timezone'    => Setting::get('app_timezone', 'America/Toronto'),
            'mail_from_email' => Setting::get('mail_from_email', 'noreply@macronstore.ca'),
            'mail_from_name'  => Setting::get('mail_from_name', config('app.name')),
            'mail_reply_to'   => Setting::get('mail_reply_to', ''),
            'mail_send_enabled' => Setting::get('mail_send_enabled', 'false') === 'true',
            'mail_transport'    => Setting::get('mail_transport', 'graph'),
            'mail_host'         => Setting::get('mail_host', 'smtp.office365.com'),
            'mail_port'         => Setting::get('mail_port', '587'),
            'mail_encryption'   => Setting::get('mail_encryption', 'tls'),
            'mail_username'     => Setting::get('mail_username', 'orders@macronstore.ca'),
            'mail_password'     => null,
            'graph_tenant_id'     => Setting::get('graph_tenant_id', ''),
            'graph_client_id'     => Setting::get('graph_client_id', ''),
            'graph_client_secret' => null,
            'admin_notification_emails'   => Setting::get('admin_notification_emails', ''),
            'notify_order_submitted'      => Setting::get('notify_order_submitted', 'true') === 'true',
            'notify_status_changed_statuses' => json_decode(Setting::get('notify_status_changed_statuses', '[]'), true) ?: [],
            'notify_order_note_added'     => Setting::get('notify_order_note_added', 'true') === 'true',
            'notify_low_stock'            => Setting::get('notify_low_stock', 'true') === 'true',
            'low_stock_threshold'         => Setting::get('low_stock_threshold', '5'),
            'summer_forecast_open'  => Setting::get('summer_forecast_open') ?: null,
            'summer_forecast_close' => Setting::get('summer_forecast_close') ?: null,
            'winter_forecast_open'  => Setting::get('winter_forecast_open') ?: null,
            'winter_forecast_close' => Setting::get('winter_forecast_close') ?: null,
            'retention'       => collect(ActivityLogResource::MODULE_LABELS)
                ->keys()
                ->mapWithKeys(fn (string $module) => [$module => (int) ($retentionByModule[$module] ?? 90)])
                ->all(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\Select::make('app_timezone')
                            ->label('Time Zone')
                            ->options(self::TIMEZONES)
                            ->required()
                            ->helperText('Every date/time shown across the app (orders, logs, exports) uses this — defaults to Canada Eastern.'),
                    ]),
                Forms\Components\Section::make('Email')
                    ->description('The "From" identity used for any outgoing order emails.')
                    ->schema([
                        Forms\Components\TextInput::make('mail_from_email')
                            ->label('Default From Email')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('mail_from_name')
                            ->label('Default From Name')
                            ->required(),
                        Forms\Components\TextInput::make('mail_reply_to')
                            ->label('Reply-To Email')
                            ->email()
                            ->helperText('Optional — leave blank to reply to the From address above.'),
                    ]),
                Forms\Components\Section::make('Mail Server (Outlook / Exchange)')
                    ->description('Connection used to actually send order emails. While disabled, nothing goes out regardless of the fields below.')
                    ->schema([
                        Forms\Components\Toggle::make('mail_send_enabled')
                            ->label('Send emails')
                            ->helperText('On = order emails are sent for real through the connection below. Off = sending is disabled entirely.')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('mail_transport')
                            ->label('Method')
                            ->options([
                                'graph' => 'Microsoft Graph API (OAuth, recommended)',
                                'smtp'  => 'SMTP (Basic Auth)',
                            ])
                            ->helperText('This tenant has SMTP AUTH disabled at the Microsoft 365 level, so Graph is the working option unless that\'s changed.')
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('mail_username')
                            ->label('Mailbox')
                            ->required()
                            ->placeholder('orders@macronstore.ca')
                            ->helperText('The mailbox sent as/through — SMTP username, or the Graph "send mail as" mailbox. Exchange rejects mail whose From doesn\'t match it.'),
                        Forms\Components\TextInput::make('mail_host')
                            ->label('Host')
                            ->required(fn (Get $get) => $get('mail_transport') === 'smtp')
                            ->visible(fn (Get $get) => $get('mail_transport') === 'smtp')
                            ->placeholder('smtp.office365.com'),
                        Forms\Components\TextInput::make('mail_port')
                            ->label('Port')
                            ->required(fn (Get $get) => $get('mail_transport') === 'smtp')
                            ->visible(fn (Get $get) => $get('mail_transport') === 'smtp')
                            ->numeric()
                            ->placeholder('587'),
                        Forms\Components\Select::make('mail_encryption')
                            ->label('Encryption')
                            ->options(['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', '' => 'None'])
                            ->required(fn (Get $get) => $get('mail_transport') === 'smtp')
                            ->visible(fn (Get $get) => $get('mail_transport') === 'smtp'),
                        Forms\Components\TextInput::make('mail_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->visible(fn (Get $get) => $get('mail_transport') === 'smtp')
                            ->helperText('Leave blank to keep the currently saved password.'),
                        Forms\Components\TextInput::make('graph_tenant_id')
                            ->label('Tenant ID')
                            ->required(fn (Get $get) => $get('mail_transport') === 'graph')
                            ->visible(fn (Get $get) => $get('mail_transport') === 'graph'),
                        Forms\Components\TextInput::make('graph_client_id')
                            ->label('Client ID (Application ID)')
                            ->required(fn (Get $get) => $get('mail_transport') === 'graph')
                            ->visible(fn (Get $get) => $get('mail_transport') === 'graph'),
                        Forms\Components\TextInput::make('graph_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->visible(fn (Get $get) => $get('mail_transport') === 'graph')
                            ->helperText('Leave blank to keep the currently saved secret. This app registration needs application-level Mail.Send permission (admin consented) for this mailbox.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Notifications')
                    ->description('Which events send an email, and who the admin-facing ones go to. Each still requires "Send emails" above to be on.')
                    ->schema([
                        Forms\Components\TextInput::make('admin_notification_emails')
                            ->label('Admin Notification Email(s)')
                            ->helperText('Comma-separated — receives order-submitted, note, and low-stock alerts.')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('notify_order_submitted')
                            ->label('Order submitted → notify admin')
                            ->helperText('When a club submits an order (Draft → Submitted).'),
                        Forms\Components\CheckboxList::make('notify_status_changed_statuses')
                            ->label('Order status changed → notify club')
                            ->helperText('Only checked statuses email the club when staff sets an order to them.')
                            ->options(OrderResource::STATUSES)
                            ->columns(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('notify_order_note_added')
                            ->label('Order note added → notify the other party')
                            ->helperText('Staff notes email the club; club notes email admin.'),
                        Forms\Components\Toggle::make('notify_low_stock')
                            ->label('Low stock alert → notify admin')
                            ->helperText('When a Logo Stock item\'s quantity drops to/below the threshold.'),
                        Forms\Components\TextInput::make('low_stock_threshold')
                            ->label('Low Stock Threshold')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->visible(fn (Get $get) => $get('notify_low_stock')),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Forecasts')
                    ->description('Default Summer/Winter Forecast submission windows — used for any club that doesn\'t have its own override dates set (Clubs > Forecast Windows). Leave blank to keep forecast submission closed by default.')
                    ->schema([
                        Forms\Components\DatePicker::make('summer_forecast_open')->label('Summer Opens'),
                        Forms\Components\DatePicker::make('summer_forecast_close')->label('Summer Closes'),
                        Forms\Components\DatePicker::make('winter_forecast_open')->label('Winter Opens'),
                        Forms\Components\DatePicker::make('winter_forecast_close')->label('Winter Closes'),
                    ])
                    ->columns(4),
                Forms\Components\Section::make('Logs')
                    ->description('The daily cron job prunes each module\'s log entries independently, once they\'re older than that module\'s number of days. Set a module to 0 to never auto-delete its logs.')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema(
                                collect(ActivityLogResource::MODULE_LABELS)
                                    ->map(fn (string $label, string $module) => Forms\Components\TextInput::make("retention.{$module}")
                                        ->label($label)
                                        ->numeric()
                                        ->minValue(0)
                                        ->required()
                                        ->suffix('days')
                                        ->helperText('0 = never'))
                                    ->values()
                                    ->all(),
                            ),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('log_retention_by_module', json_encode($data['retention'] ?? []));
        Setting::set('app_timezone', $data['app_timezone']);
        Setting::set('mail_from_email', $data['mail_from_email']);
        Setting::set('mail_from_name', $data['mail_from_name']);
        Setting::set('mail_reply_to', $data['mail_reply_to'] ?? '');
        Setting::set('mail_send_enabled', $data['mail_send_enabled'] ? 'true' : 'false');
        Setting::set('mail_transport', $data['mail_transport']);
        Setting::set('mail_host', $data['mail_host'] ?? '');
        Setting::set('mail_port', $data['mail_port'] ?? '');
        Setting::set('mail_encryption', $data['mail_encryption'] ?? '');
        Setting::set('mail_username', $data['mail_username']);
        Setting::set('graph_tenant_id', $data['graph_tenant_id'] ?? '');
        Setting::set('graph_client_id', $data['graph_client_id'] ?? '');

        if (filled($data['mail_password'] ?? null)) {
            Setting::setEncrypted('mail_password', $data['mail_password']);
        }

        if (filled($data['graph_client_secret'] ?? null)) {
            Setting::setEncrypted('graph_client_secret', $data['graph_client_secret']);
        }

        Setting::set('admin_notification_emails', $data['admin_notification_emails'] ?? '');
        Setting::set('notify_order_submitted', $data['notify_order_submitted'] ? 'true' : 'false');
        Setting::set('notify_status_changed_statuses', json_encode(array_values($data['notify_status_changed_statuses'] ?? [])));
        Setting::set('notify_order_note_added', $data['notify_order_note_added'] ? 'true' : 'false');
        Setting::set('notify_low_stock', $data['notify_low_stock'] ? 'true' : 'false');
        Setting::set('low_stock_threshold', $data['low_stock_threshold'] ?? '5');

        Setting::set('summer_forecast_open', $data['summer_forecast_open'] ?? '');
        Setting::set('summer_forecast_close', $data['summer_forecast_close'] ?? '');
        Setting::set('winter_forecast_open', $data['winter_forecast_open'] ?? '');
        Setting::set('winter_forecast_close', $data['winter_forecast_close'] ?? '');

        date_default_timezone_set($data['app_timezone']);
        config(['app.timezone' => $data['app_timezone']]);
        MailConfigurator::apply();

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendTestEmail')
                ->label('Send Test Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->action(function () {
                    MailConfigurator::apply();

                    if (Setting::get('mail_send_enabled', 'false') !== 'true') {
                        Notification::make()->title('Sending is disabled')->body('Turn on "Send emails" and save before sending a test.')->warning()->send();

                        return;
                    }

                    $to = Setting::get('mail_from_email');

                    try {
                        Mail::raw('This is a test email from the Macron Club Order System mail settings page.', function ($message) use ($to) {
                            $message->to($to)->subject('Macron Club Order System — Test Email');
                        });

                        Notification::make()->title("Test email sent to {$to}")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Test email failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_logs') ?? false;
    }
}
