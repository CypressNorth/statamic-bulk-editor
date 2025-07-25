<?php

namespace CypressNorth\StatamicBulkEditor\Actions;

use Illuminate\Support\Collection;
use Statamic\Actions\Action;
use Statamic\Entries\Entry;
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
        'slug', // controlled by the filename and should always be unique... probably shouldn't ever be supported
    ];

    protected ?string $type;
    protected ?string $facade = null;

    public function buttonText()
    {
        /** @translation */
        return 'Bulk Edit|Bulk Edit :count Entries';
    }

    public function confirmationText()
    {
        /** @translation */
        return 'Edit the fields you\'d like changed for these entries.';
    }

    public function warningText()
    {
        return "You're editing :count entries. This action cannot be undone by the Bulk Editor addon.";
    }

    public static function getAllAvailableFields(string $for, bool $includingUnsupported = false, string $containerType = 'collection')
    {
        $handle = $for;

        $facade = static::findFacade($containerType);

        try {
            $blueprintMethod = static::getBlueprintsMethodForContainerType($containerType);
            $fields = collect($facade::findByHandle($handle)->{$blueprintMethod}())
                ->map(fn($v) => $v->fields()->items())
                ->flatten(1);
        } catch (\Error) {
            return collect();
        }

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
        // disable running on individual items (i.e. outside of the bulk select menu)
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
        if (is_null($this->type())) {
            return false;
        }

        /** @var Collection $items */
        $types = $items->reduce(function ($carry, $item, $index) {
            /** @var Entry $item */
            $carry['blueprints'][$item->blueprint()?->handle() ?: $index] = true;
            if (! method_exists($item, $handleMethod = $this->type() . "Handle")) {
                $carry['collections'][null] = true;
            } else {
                $carry['collections'][$item->{$handleMethod}()] = true;
            }
            return $carry;
        }, ['blueprints' => [], 'collections' => []]);

        return count($types['collections']) === 1                           // all matching collections
            && isset($types['collections'][$this->context[$this->type()]])    // all from this collection
            && ($this->getFillable())                                       // Collection has fillable fields
            && count($types['blueprints']) === 1;                           // all matching blueprints
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
                if ($value && $value !== static::NO_VALUE_CHANGE) {
                    if ($key === 'date') {
                        $item->date(strtr($value, [' ' => '-', ':' => '']));
                        continue;
                    }
                    $item->set($key, $value);
                }
            }
            $item->save();
        }
    }

    protected function facade()
    {
        return $this->facade ?? ($this->facade = static::findFacade($this->type()));
    }

    protected function fieldItems()
    {
        try {
            $itemContainer = $this->facade()::findByHandle($this->context[$this->type()]);

            $blueprintMethod = static::getBlueprintsMethodForContainerType($this->type());
            /** @var Collection $blueprints */
            $blueprints = collect($itemContainer->{$blueprintMethod}());
        } catch (\Error) {
            return [];
        }

        $fields = [];

        $blueprintFields = $blueprints
            ->map(fn($v) => $v->fields()->items())
            ->flatten(1)
            ->mapWithKeys(function ($value) {
                $field = $value["field"] ?? null;

                while (is_string($field)) {
                    // Field is a Fieldset that should be loaded in
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

    protected function getFillable()
    {
        try {
            $itemContainer = $this->facade()::find($this->context[$this->type()]);

            if ($itemContainer) {
                return $itemContainer->cascade('cn_bulk_editor-editable_fields');
            }

            return [];
        } catch (\Error) {
            return [];
        }
    }

    protected function convertStringFieldToFieldsetFields(string $field_value)
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

    protected function type(): ?string
    {
        return $this->type ?? ($this->type = $this->determineType());
    }

    protected function determineType(): ?string
    {
        if ($this->context['collection'] ?? null) {
            return "collection";
        }

        if ($this->context['taxonomy'] ?? null) {
            return "taxonomy";
        }

        return null;
    }

    public static function findFacade(?string $containerType): ?string
    {
        if (is_null($containerType)) {
            return null;
        }

        $facade = "\\Statamic\\Facades\\" . ucfirst($containerType);

        if (! class_exists($facade)) {
            return null;
        }

        return $facade;
    }

    protected static function getBlueprintsMethodForContainerType(string $containerType): string
    {
        return match ($containerType) {
            'taxonomy' => 'term',
            default => 'entry',
        } . "Blueprints";
    }
}
