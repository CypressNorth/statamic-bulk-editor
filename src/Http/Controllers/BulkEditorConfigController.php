<?php

namespace CypressNorth\StatamicBulkEditor\Http\Controllers;

use CypressNorth\StatamicBulkEditor\Config\Blueprint as ConfigBlueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Facades;
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
        $values = $fields->process()->values();
        /** @var Collection $values */

        // Do something with the values. Here we'll update the product model.
        foreach ($values->get('editable', []) as $collection => $fields) {
            $collection = Facades\Collection::findByHandle($collection);
            $collection->cascade()->put('cn_bulk_editor-editable_fields', $fields);
            $collection->save();
        }

        // Return something if you want. But it's not even necessary.
    }

    protected function createBlueprint(): StatamicBlueprint
    {
        return Facades\Blueprint::make()->setContents([
            'fields' => Arr::get(ConfigBlueprint::getBlueprint()->contents(), 'tabs.main.sections.0.fields'),
        ]);
    }
}
