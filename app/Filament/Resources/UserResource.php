<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\UserInvitations;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getModelLabel(): string
    {
        return __('panel.resource.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.resource.users');
    }

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('panel.user.name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('panel.user.email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->label(__('panel.user.role'))
                    ->options(collect(User::ROLES)->mapWithKeys(fn ($r) => [$r => __('panel.user.roles.'.$r)])->all())
                    ->helperText(__('panel.user.role_hint'))
                    ->required()
                    ->native(false)
                    ->default('agent'),
                Forms\Components\TextInput::make('password')
                    ->label(__('panel.user.password'))
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->maxLength(255)
                    ->helperText(fn (string $operation) => __($operation === 'create' ? 'panel.user.password_hint_create' : 'panel.user.password_hint')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('panel.user.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('panel.field.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('panel.user.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('panel.user.roles.'.$state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('panel.user.status'))
                    ->badge()
                    ->state(fn (User $record) => $record->hasPendingInvitation() ? 'pending' : 'active')
                    ->formatStateUsing(fn (string $state) => __('panel.user.status_'.$state))
                    ->color(fn (string $state) => $state === 'pending' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    static::resendInvitationAction(),
                    static::copyInvitationLinkAction(),
                ])->label(__('panel.user.invite_group'))->icon('heroicon-o-envelope')->button()->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Email the invitation; if the mail cannot go out, say so and point to the copy-link fallback. */
    public static function sendInvitation(User $user): void
    {
        try {
            UserInvitations::send($user);
        } catch (\Throwable $e) {
            report($e);

            Notification::make()->warning()->persistent()
                ->title(__('panel.user.invite_failed'))
                ->body(__('panel.user.invite_failed_body', ['reason' => str($e->getMessage())->limit(160)]))
                ->send();

            return;
        }

        Notification::make()->success()->title(__('panel.user.invite_sent', ['email' => $user->email]))->send();
    }

    public static function resendInvitationAction(string $actionClass = Tables\Actions\Action::class)
    {
        return $actionClass::make('resendInvitation')
            ->label(fn (User $record) => __($record->hasPendingInvitation() ? 'panel.user.invite_resend' : 'panel.user.invite_send'))
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalDescription(__('panel.user.invite_resend_confirm'))
            ->visible(fn (User $record) => auth()->user()->can('update', $record))
            ->action(fn (User $record) => static::sendInvitation($record));
    }

    /** For when the email does not arrive (or the password was lost): a fresh link to hand over by other means. */
    public static function copyInvitationLinkAction(string $actionClass = Tables\Actions\Action::class)
    {
        return $actionClass::make('copyInvitationLink')
            ->label(__('panel.user.invite_copy'))
            ->icon('heroicon-o-link')
            ->visible(fn (User $record) => auth()->user()->can('update', $record))
            ->action(function (User $record) {
                $url = UserInvitations::linkToShare($record);

                Notification::make()->success()->persistent()
                    ->title(__('panel.user.invite_link_title'))
                    ->body(new HtmlString('<span style="word-break:break-all">'.e($url).'</span><br>'.e(__('panel.user.invite_link_body', ['name' => $record->name]))))
                    ->actions([
                        NotificationAction::make('copy')
                            ->label(__('panel.user.invite_link_copy'))
                            ->button()
                            ->alpineClickHandler('navigator.clipboard.writeText('.Js::from($url)->toHtml().')'),
                    ])
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
