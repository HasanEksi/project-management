<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\Project;
use Filament\Pages\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
            ->visibleTo(auth()->user());
    }

    public function getTableTabs(): array
    {
        $projects = Project::when(!auth()->user()->isAdmin(), function (Builder $query) {
                return $query->whereHas('tickets', function (Builder $query) {
                    return $query->visibleTo(auth()->user());
                });
            })
            ->get();

        $tabs = [
            'all' => Tab::make('Tümü'),
        ];

        foreach ($projects as $project) {
            $tabs[$project->id] = Tab::make($project->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('project_id', $project->id));
        }

        return $tabs;
    }
}
