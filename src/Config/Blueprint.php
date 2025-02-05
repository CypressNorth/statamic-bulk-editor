<?php

namespace CypressNorth\StatamicBulkEditor\Config;

use CypressNorth\StatamicBulkEditor\Actions\EditInBulk;
use Statamic\Entries\Collection as EntriesCollection;
use Statamic\Facades;
use Statamic\Facades\Collection;

class Blueprint
{
    public static function getBlueprint(): \Statamic\Fields\Blueprint
    {
        $fields = [];

        foreach (Collection::all() as $collection) {
            /** @var EntriesCollection $collection */
            $fields[] = [
                'handle' => $collection->handle(),
                'field' => [
                    'type' => 'select',
                    'options' => [
                        // all field handles for this collection's blueprints
                        ...EditInBulk::getAllAvailableFields(for: $collection->handle())
                            ->filter(function ($v) {
                                if (!is_array($field = $v['field'])) {
                                    return true;
                                }
                                return $field['type'] !== 'hidden';
                            })
                            ->pluck('handle')
                    ],
                    'default' => $collection->cascade('cn_bulk_editor-editable_fields'),
                    'multiple' => true,
                ]
            ];
        }

        return Facades\Blueprint::make('import-blueprint')->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'editable',
                                    'field' => [
                                        'type' => 'group',
                                        'instructions' => 'Choose which fields from each collection should be editable in bulk. Only those chosen here will appear in the bulk editor.',
                                        'fields' => $fields
                                    ]
                                ]
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
