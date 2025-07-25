<?php

namespace CypressNorth\StatamicBulkEditor\Http\Controllers;

use CypressNorth\StatamicBulkEditor\Actions\EditInBulk;
use CypressNorth\StatamicBulkEditor\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Http\Controllers\CP\CpController;

class BulkEditorConfigController extends CpController
{
    public function index()
    {
        $blueprint = $this->createBlueprint();

        return view('cn-bulk-editor::index', [
            'blueprint' => $blueprint->toPublishArray(),
            'meta' => $blueprint->fields()->meta(),
            'values' => $blueprint->fields()->preProcess()->values()->all(),
        ]);
    }

    public function update(Request $request)
    {
        $blueprint = $this->createBlueprint();

        // Get a Fields object, and populate it with the submitted values.
        $fields = $blueprint->fields()->addValues($request->all());

        // Perform validation. Like Laravel's standard validation, if it fails,
        // a 422 response will be sent back with all the validation errors.
        $fields->validate();

        // Perform post-processing. This will convert values the Vue components
        // were using into values suitable for putting into storage.
        $values = $fields
            ->process()
            ->values()
            // Only grab editable field groups
            ->where(fn($_, $k) => str_starts_with($k, "editable_"))
            // Flatten editable field groups such that their fields are all at the top level
            ->reduce(function (Collection $carry, $value) {
                foreach ($value as $field => $value) {
                    if (!is_array($value)) {
                        // skip non-array values
                        continue;
                    }

                    $carry->put($field, $value);
                }

                return $carry;
            }, collect());

        foreach ($values as $identifier => $fields) {
            $parts = explode("_", $identifier, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$containerType, $containerInstanceName] = $parts;

            if (! $facade = EditInBulk::findFacade($containerType)) {
                continue;
            }

            $collection = $facade::findByHandle($containerInstanceName);
            $collection->cascade()->put('cn_bulk_editor-editable_fields', $fields);
            $collection->save();
        }

        // Return something if you want. But it's not even necessary.
    }

    protected function createBlueprint(): StatamicBlueprint
    {
        return \Statamic\Facades\Blueprint::make()->setContents([
            'tabs' => [
                'main' => [
                    'sections' => Config\BlueprintSection::prepareAllSections(),
                ],
            ],
        ]);
    }
}
