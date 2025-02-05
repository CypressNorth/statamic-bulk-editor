<?php

namespace CypressNorth\StatamicBulkEditor\Actions;

use Illuminate\Support\Collection;
use Statamic\Actions\Action;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as StatamicCollection;
use Statamic\Facades\Fieldset;

class EditInBulk extends Action
{
    const string NO_VALUE_CHANGE = "cn_bulk_editor-no_value_change";

    /**
     * Each of these properties are set in unique ways.
     * They won't be supported for now
     */
    const array UNSUPPORTED = [
        'parent',
        'slug',
    ];

    public static function getAllAvailableFields(string $for, bool $includingUnsupported = false)
    {
        $handle = $for;
        $fields = collect(StatamicCollection::findByHandle($handle)->entryBlueprints())
            ->map(fn($v) => $v->fields()->items())
            ->flatten(1);

        if (! $includingUnsupported) {
            $fields = $fields->filter(
                fn($v) =>
                !in_array($v['handle'] ?? null, static::UNSUPPORTED)
            );
        }

        return $fields;
    }

    public function visibleTo($item)
    {
        // disable individual runs
        return false;
    }

    /**
     * Determines whether or not this action may run for this list of items.
     *
     * @var Collection $items The list of items
     * @return void
     */
    public function visibleToBulk($items)
    {
        /** @var Collection $items */
        $types = $items->reduce(function ($carry, $item, $index) {
            /** @var Entry $item */
            $carry['blueprints'][$item->blueprint()?->handle() ?: $index] = true;
            $carry['collections'][$item->collectionHandle()] = true;
            return $carry;
        }, ['blueprints' => [], 'collections' => []]);

        $collectionHandle = $this->context['collection'];

        return count($types['collections']) === 1               // all matching collections
            && isset($types['collections'][$collectionHandle])  // all from this collection
            && ($this->getFillable())       // Collection has fillable fields
            && count($types['blueprints']) === 1;               // all matching blueprints
    }

    /**
     * The run method
     *
     * @return mixed
     */
    public function run($items, $values)
    {
        unset($values['blueprint']); // Remove blueprint from info

        $values = collect($values)
            ->only($this->getFillable());

        foreach ($items as $item) {
            /** @var Entry $item */
            foreach ($values as $key => $value) {
                dump($key, $value, $item->get($key));
                if ($value && $value !== static::NO_VALUE_CHANGE) {
                    $item->set($key, $value);
                }
            }
            $item->save();
        }
    }


    protected function fieldItems()
    {
        $collection = StatamicCollection::findByHandle($collectionHandle = $this->context['collection']);
        /** @var Collection $blueprints */
        $blueprints = collect($collection->entryBlueprints());

        $fields = [];

        if ($blueprints->count() > 1) {
            // $options = $blueprints->pluck('title'); // no longer supporting multiple blueprints
            $fields['message'] = [
                'type' => 'section',
                'display' => 'Warning',
                'instructions' => 'Collections with multiple blueprints are not supported, but you can edit the following fields at your own risk.',
            ];
        }

        $blueprintFields = $blueprints
            ->map(fn($v) => $v->fields()->items())
            ->flatten(1)
            ->mapWithKeys(function ($value) {
                $field = $value["field"] ?? null;
                while (is_string($field)) {
                    /**
                     * Field is a Fieldset that should be loaded in
                     *
                     * Technically we could just pass the string here,
                     * but we might want to inject our own data (i.e. options)
                     * into the fields later
                     */
                    $field = $this->convertStringFieldToFieldsetFields($field);
                }

                if (! is_array($field)) {
                    // Unknown field format
                    return [null => null];
                }

                if (isset($field['options'])) {
                    array_unshift($field['options'], ['key' => static::NO_VALUE_CHANGE, 'value' => 'No Change']);
                    $field['default'] = static::NO_VALUE_CHANGE;
                }
                $field['required'] = false;
                $field['validate'] = [];
                return [
                    $value["handle"] => $field
                ];
            })
            // ->filter(fn($v, $key) => $key != null) // for when fillable whitelist is inactive
            ->only($this->getFillable()) // for when fillable whitelist is active
            ->toArray();

        $fields = array_merge($fields, $blueprintFields);

        return $fields;
    }

    private function getFillable()
    {
        $collection = StatamicCollection::find($this->context['collection']);
        return $collection ? $collection->cascade('cn_bulk_editor-editable_fields') : [];
    }

    private function convertStringFieldToFieldsetFields(string $field_value)
    {
        $fieldsetParts = explode('.', $field_value, 2);
        $fieldsetHandle = $fieldsetParts[0];
        $fieldHandle = $fieldsetParts[1] ?? null;

        $fieldset = Fieldset::find($fieldsetHandle);
        if (! $fieldset || ! $fieldHandle) {
            // TODO: Support multi-field fieldset links
            return null;
        }

        $fieldsetFields = $fieldset->fields()->items();

        return $fieldsetFields
            ->first(fn($v) => ($v['handle'] ?? null) === $fieldHandle)['field'] ?? [];
    }
}
