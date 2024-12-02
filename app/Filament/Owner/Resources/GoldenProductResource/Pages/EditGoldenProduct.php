<?php

namespace App\Filament\Owner\Resources\GoldenProductResource\Pages;

use App\Filament\Owner\Resources\GoldenProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoldenProduct extends EditRecord
{
    protected static string $resource = GoldenProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
