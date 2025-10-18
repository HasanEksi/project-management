<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\Project;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function shouldPersistTableFiltersInSession(): bool
    {
        return true;
    }

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where(function ($query) {
                return $query->where('owner_id', auth()->user()->id)
                    ->orWhere('responsible_id', auth()->user()->id)
                    ->orWhereHas('project', function ($query) {
                        return $query->where('owner_id', auth()->user()->id)
                            ->orWhereHas('users', function ($query) {
                                return $query->where('users.id', auth()->user()->id);
                            });
                    });
            });
    }

    public function getTabs(): array
    {
        $projects = Project::where('owner_id', auth()->user()->id)
            ->orWhereHas('users', function ($query) {
                return $query->where('users.id', auth()->user()->id);
            })
            ->get();

        $tabs = [
            'all' => 'Tümü',
        ];

        foreach ($projects as $project) {
            $tabs[$project->id] = $project->name;
        }

        return $tabs;
    }

    public function getActiveTab(): string
    {
        return request()->get('project', 'all');
    }
}
