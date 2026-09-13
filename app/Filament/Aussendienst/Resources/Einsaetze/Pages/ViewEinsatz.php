<?php

namespace App\Filament\Aussendienst\Resources\Einsaetze\Pages;

use App\Events\InspectionCompleted;
use App\Filament\Aussendienst\Resources\Einsaetze\EinsatzResource;
use App\Models\Attachment;
use App\Models\Inspection;
use App\Models\Vorgang;
use App\Models\VorgangNote;
use App\Models\WorkflowStatus;
use App\Services\Workflow\WorkflowService;
use App\Support\ActionNeeded;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

class ViewEinsatz extends ViewRecord
{
    protected static string $resource = EinsatzResource::class;

    public function getTitle(): string
    {
        /** @var Vorgang $record */
        $record = $this->getRecord();

        return $record->reference();
    }

    public function getSubheading(): ?string
    {
        /** @var Vorgang $record */
        $record = $this->getRecord();

        return $record->reported_species_raw ?: 'Art unbekannt';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->recordResultAction(),
            $this->uploadPhotosAction(),
            $this->addNoteAction(),
            $this->transitionAction(),
        ];
    }

    /**
     * The on-site result. Writes an Inspection - the per-visit record - and
     * mirrors the summary onto the Vorgang so the office list stays readable.
     */
    private function recordResultAction(): Action
    {
        return Action::make('record_result')
            ->label('Ergebnis erfassen')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('primary')
            ->visible(fn (Vorgang $record) => auth()->user()?->can('inspect', $record) ?? false)
            ->schema([
                DatePicker::make('inspected_at')
                    ->label('Datum der Kontrolle')
                    ->default(now())
                    ->required()
                    ->native(false)
                    ->displayFormat('d.m.Y'),

                Select::make('confirmation')
                    ->label('Vorkommen bestätigt?')
                    ->options(Inspection::confirmationOptions())
                    ->required(),

                Select::make('species_confirmed_id')
                    ->label('Tatsächliche Art')
                    ->relationship('species', 'name_de')
                    ->searchable()
                    ->preload()
                    ->placeholder('wie gemeldet'),

                Select::make('action_needed')
                    ->label('Handlungsbedarf')
                    ->options(ActionNeeded::options())
                    ->required(),

                Textarea::make('recommended_measure')
                    ->label('Empfohlene Maßnahme')
                    ->rows(3),

                Textarea::make('notes')
                    ->label('Bemerkung')
                    ->rows(3),
            ])
            ->action(function (Vorgang $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $inspection = Inspection::create([
                        'vorgang_id' => $record->getKey(),
                        'inspector_id' => auth()->id(),
                        'inspected_at' => $data['inspected_at'],
                        'confirmation' => $data['confirmation'],
                        'species_confirmed_id' => $data['species_confirmed_id'] ?? null,
                        'recommended_measure' => $data['recommended_measure'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'completed_at' => now(),
                    ]);

                    // Keep the Vorgang summary in step with the latest visit -
                    // these are the columns the office list and the legacy
                    // monitor export read.
                    $record->forceFill([
                        'visited' => true,
                        'visit_date' => $data['inspected_at'],
                        'confirmed' => $data['confirmation'] === Inspection::CONFIRMED,
                        'action_needed' => $data['action_needed'],
                        'measure' => $data['recommended_measure'] ?? $record->measure,
                        'editor_name' => auth()->user()?->name,
                    ])->save();

                    InspectionCompleted::dispatch($inspection);
                });

                Notification::make()
                    ->success()
                    ->title('Ergebnis gespeichert')
                    ->body('Der Innendienst wurde informiert.')
                    ->send();
            });
    }

    private function uploadPhotosAction(): Action
    {
        return Action::make('upload_photos')
            ->label('Fotos hochladen')
            ->icon('heroicon-o-camera')
            ->visible(fn (Vorgang $record) => auth()->user()?->can('uploadAttachment', $record) ?? false)
            ->schema([
                FileUpload::make('photos')
                    ->label('Fotos')
                    ->image()
                    ->multiple()
                    ->maxFiles(8)
                    ->maxSize(12 * 1024)
                    // Private disk: these photos often show private property.
                    ->disk(config('meldestelle.attachment_disk', 'local'))
                    ->directory('aussendienst')
                    ->visibility('private')
                    ->helperText('Auf dem Handy öffnet sich direkt die Kamera.')
                    ->required(),
            ])
            ->action(function (Vorgang $record, array $data): void {
                $disk = config('meldestelle.attachment_disk', 'local');

                foreach ($data['photos'] ?? [] as $path) {
                    Attachment::create([
                        'attachable_type' => $record->getMorphClass(),
                        'attachable_id' => $record->getKey(),
                        'disk' => $disk,
                        'path' => $path,
                        'original_filename' => basename($path),
                        'mime' => 'image/jpeg',
                        'source' => Attachment::SOURCE_AUSSENDIENST,
                        'uploaded_by_id' => auth()->id(),
                        'taken_at' => now(),
                    ]);
                }

                Notification::make()->success()->title('Fotos gespeichert')->send();
            });
    }

    private function addNoteAction(): Action
    {
        return Action::make('add_note')
            ->label('Notiz')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (Vorgang $record) => auth()->user()?->can('addNote', $record) ?? false)
            ->schema([
                Textarea::make('body')->label('Notiz')->rows(4)->required(),
            ])
            ->action(function (Vorgang $record, array $data): void {
                VorgangNote::create([
                    'vorgang_id' => $record->getKey(),
                    'user_id' => auth()->id(),
                    'body' => $data['body'],
                    'visibility' => VorgangNote::VISIBILITY_AUSSENDIENST,
                ]);

                Notification::make()->success()->title('Notiz gespeichert')->send();
            });
    }

    private function transitionAction(): Action
    {
        return Action::make('transition')
            ->label('Status ändern')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (Vorgang $record) => app(WorkflowService::class)
                ->availableTransitions($record, auth()->user())
                ->isNotEmpty())
            ->schema([
                Select::make('to_status_id')
                    ->label('Neuer Status')
                    ->required()
                    ->options(fn (Vorgang $record) => app(WorkflowService::class)
                        ->availableTransitions($record, auth()->user())
                        ->mapWithKeys(fn ($t) => [$t->to_status_id => $t->label])),

                Textarea::make('note')->label('Begründung')->rows(3),
            ])
            ->action(function (Vorgang $record, array $data): void {
                $target = WorkflowStatus::find($data['to_status_id']);

                if ($target === null) {
                    return;
                }

                try {
                    app(WorkflowService::class)->transition(
                        $record, $target, auth()->user(), $data['note'] ?? null,
                    );
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Nicht möglich')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Status geändert')->send();
            });
    }
}
