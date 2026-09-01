<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Support\Modules;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading('Ainda sem contas além da sua')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (User $record) => $record->getKey() === Auth::id() ? 'a sua conta' : null),
                TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->label('Administrador')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean(),
                TextColumn::make('modules')
                    ->label('Módulos')
                    ->state(fn (User $record) => $record->isAdmin() ? 'Todos' : (Modules::forUser($record)->pluck('name')->implode(', ') ?: '—')),
                TextColumn::make('last_login_at')
                    ->label('Última entrada')
                    ->since()
                    ->placeholder('nunca')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Criada')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Administrador'),
            ])
            ->recordActions([
                EditAction::make(),
                // Apagar a própria conta deixaria a pessoa de fora a meio do
                // trabalho — e, se fosse o último administrador, ninguém
                // conseguiria voltar a criar contas.
                DeleteAction::make()
                    ->visible(fn (User $record) => $record->getKey() !== Auth::id()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            $records->reject(fn (User $u) => $u->getKey() === Auth::id())
                                ->each->delete();
                        }),
                ]),
            ]);
    }
}
