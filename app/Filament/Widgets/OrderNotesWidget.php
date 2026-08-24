<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Support\OrderNotifier;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Widgets\Widget;

class OrderNotesWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.order-notes';

    protected int|string|array $columnSpan = 'full';

    public ?Order $record = null;

    public ?array $data = ['note' => ''];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('note')
                    ->label('')
                    ->placeholder('Add a note…')
                    ->rows(2)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function getNotes()
    {
        if (! $this->record) {
            return collect();
        }

        return $this->record->orderNotes()->with('user')->orderBy('created_at')->get();
    }

    public function addNote(): void
    {
        if (! $this->record) {
            return;
        }

        $data = $this->form->getState();

        $note = $this->record->orderNotes()->create([
            'user_id' => auth()->id(),
            'note'    => $data['note'],
        ]);

        OrderNotifier::noteAdded($note);

        $this->form->fill();
    }
}
