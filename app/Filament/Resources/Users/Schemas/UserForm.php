<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User as UserModel;
use Filament\Forms\Components as FC;      // поля формы
use Filament\Schemas\Components as SC;    // Section / Tabs / Wizard
use Filament\Schemas\Schema;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Profile')
                ->columnSpanFull()   // 🔹 на всю ширину
                ->columns(2)
                ->schema([
                    FC\TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255),

                    FC\TextInput::make('full_name')
                        ->label('Full name')
                        ->maxLength(255),

                    FC\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),

                    FC\Select::make('role')
                        ->label('Role')
                        ->options(UserModel::roleOptions())
                        ->required()
                        ->native(false)
                        ->preload(),

                    FC\FileUpload::make('avatar')
                        ->label('Avatar')
                        ->image()
                        ->directory('avatars')
                        ->disk('public')
                        ->visibility('public')
                        ->imageEditor()
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('1:1')
                        ->imagePreviewHeight('auto')
                        ->columnSpanFull()
                        ->panelLayout('integrated')   // 🔹 превью встроенное, как в Games
                        ->maxSize(2048)
                        ->nullable()
                        ->helperText('PNG/JPG, ≤ 2 MB'),
                ]),
        ]);
    }
}