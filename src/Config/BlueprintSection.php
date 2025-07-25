<?php

namespace CypressNorth\StatamicBulkEditor\Config;

use CypressNorth\StatamicBulkEditor\Actions\EditInBulk;
use Illuminate\Support\Str;

use function Illuminate\Log\log;

class BlueprintSection
{
    protected static array $containerTypes = [
        'collection',
        'taxonomy',
    ];

    public function __construct(protected string $containerType) {}

    public static function for(string $containerType): ?array
    {
        if (! in_array($containerType, static::$containerTypes)) {
            log()->warning("Provided container type is not supported.", ['containerType' => $containerType]);
            return null;
        }

        return (new static($containerType))->generate();
    }

    public static function prepareAllSections(): array
    {
        return array_reduce(static::$containerTypes, function ($carry, $type) {
            $section = static::for($type);
            if (! is_null($section)) {
                $carry[] = $section;
            }
            return $carry;
        }, []);
    }

    public function handleMethod(): string
    {
        return match ($this->containerType) {
            // 'asset' => 'containerHandle',
            default => 'handle',
        };
    }

    protected function generate(): ?array
    {
        $fields = [];
        $containerType = $this->containerType;

        $facade = EditInBulk::findFacade($containerType);

        try {
            $allInContainer = $facade::all();
        } catch (\Error) {
            log()->warning("Provided $containerType does not have a predictable Statamic Facade.", ['containerType' => $containerType]);
            return null;
        }

        foreach ($allInContainer as $item) {
            $handleMethod = $this->handleMethod();
            if (! method_exists($item, $handleMethod)) {
                log()->warning(
                    "Provided $containerType does not have a $handleMethod method.",
                    [$containerType => $item, 'containerType' => $containerType]
                );
                continue;
            }

            if (! method_exists($item, 'cascade')) {
                log()->warning(
                    "Provided $containerType does not have a cascade method.",
                    [$containerType => $item, 'containerType' => $containerType]
                );
                continue;
            }

            $fields[] = [
                'handle' => $containerType . "_" . $item->$handleMethod(),
                'field' => [
                    'display' => Str::headline($item->$handleMethod()),
                    'type' => 'select',
                    'options' => [
                        // all field handles for this container's blueprints
                        ...EditInBulk::getAllAvailableFields(for: $item->$handleMethod(), containerType: $containerType)
                            ->filter(function ($v) {
                                $field = $v['field'] ?? null;
                                if (is_null($field)) {
                                    return false;
                                }
                                if (is_array($field)) {
                                    return $field['type'] !== 'hidden';
                                }
                                return true;
                            })
                            ->pluck('handle')
                    ],
                    'default' => $item->cascade('cn_bulk_editor-editable_fields'),
                    'multiple' => true,
                ]
            ];
        }

        return [
            'handle' => $containerType,
            'fields' => [
                [
                    'handle' => "editable_{$containerType}",
                    'field' => [
                        'display' => 'Editable ' . Str::headline($containerType) . ' Fields',
                        'type' => 'group',
                        'instructions' => "Choose which fields from each {$containerType} should be editable in bulk. Only those chosen here will appear in the bulk editor.",
                        'fields' => $fields
                    ]
                ]
            ],
        ];
    }
}
