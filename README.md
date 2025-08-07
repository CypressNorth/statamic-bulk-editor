# Statamic Bulk Editor

> Statamic Bulk Editor lets you edit several of your Statamic collection entries at once.

## Features

- Quickly edit the same fields from your blueprints, but across several entries at once
- Specify which fields can be edited on each collection

## How to Install

You can install this addon via Composer:

```bash
composer require cypressnorth/statamic-bulk-editor
```

## How to Use

Go to Utilities -> Bulk Editor to specify which fields on each of your collections may be edited with the bulk editor.

## Caveats

The Bulk Edit action will only appear if the entries you've selected use the same blueprint.
- Prevents editing fields that have the same name but different purposes depending on the blueprint
