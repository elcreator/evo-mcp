<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use EvolutionCMS\eMCP\Support\ElementTypes;
use EvolutionCMS\eMCP\Tools\Elements\ElementsGetTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.write.elements.save')]
#[Description('Create or update a template, TV, chunk, snippet or plugin. Requires new_*/save_* permission for the type; fires the same OnBefore*FormSave/On*FormSave events as the manager.')]
class ElementsSaveTool extends BaseWriteTool
{
    /** @var array<string, int> manager_log action ids per element type */
    private const LOG_ACTIONS = ['template' => 20, 'tv' => 302, 'chunk' => 79, 'snippet' => 24, 'plugin' => 103];

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['nullable', 'integer', 'min:1'],
            'fields' => ['required', 'array'],
        ]);

        $spec = ElementTypes::get((string)$validated['type']);
        $type = $spec['type'];
        $id = isset($validated['id']) ? (int)$validated['id'] : null;
        $fields = $this->pickFields((array)$validated['fields'], $spec['write']);

        if ($fields === []) {
            throw ValidationException::withMessages(['fields' => 'Nothing to save.']);
        }

        $nameField = $spec['name_field'];
        /** @var class-string<Model> $model */
        $model = $spec['model'];
        [$beforeEvent, $afterEvent] = $spec['events'];

        if ($id === null) {
            $this->requirePermission($spec['permissions']['new']);

            $name = trim((string)($fields[$nameField] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(['fields' => "Field [{$nameField}] is required to create a {$type}."]);
            }
            if ($model::query()->where($nameField, $name)->exists()) {
                throw ValidationException::withMessages(['fields' => "A {$type} named [{$name}] already exists."]);
            }

            evo()->invokeEvent($beforeEvent, ['mode' => 'new', 'id' => 0]);
            $row = $model::query()->create($fields);
            $mode = 'new';
        } else {
            $this->requirePermission($spec['permissions']['save']);

            $row = ElementsGetTool::find($spec, $id, null);
            if ($row === null) {
                throw ValidationException::withMessages(['id' => ucfirst($type) . " [{$id}] not found."]);
            }
            $this->requireUnlocked((int)$spec['lock_type'], $id);

            if (isset($fields[$nameField])) {
                $name = trim((string)$fields[$nameField]);
                $duplicate = $model::query()->where($nameField, $name)->where('id', '!=', $id)->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['fields' => "A {$type} named [{$name}] already exists."]);
                }
            }

            evo()->invokeEvent($beforeEvent, ['mode' => 'upd', 'id' => $id]);
            $row->update($fields);
            $mode = 'upd';
        }

        $rowId = (int)$row->getKey();
        $_SESSION['itemname'] = (string)$row->getAttribute($nameField);
        evo()->invokeEvent($afterEvent, ['mode' => $mode, 'id' => $rowId]);
        evo()->clearCache('full');

        $this->logManagerAction(
            self::LOG_ACTIONS[$type],
            ($mode === 'new' ? 'Created ' : 'Saved ') . $type . ' via MCP',
            $rowId,
            (string)$row->getAttribute($nameField)
        );

        return $this->respondItem([
            'type' => $type,
            'id' => $rowId,
            'name' => $row->getAttribute($nameField),
            'mode' => $mode === 'new' ? 'created' : 'updated',
            'updated_fields' => array_keys($fields),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(ElementTypes::names())->required(),
            'id' => $schema->integer()->min(1)->description('Omit to create a new element'),
            'fields' => $schema->object()->required()->description('Columns to write; code lives in content (template), default_text (tv), snippet (chunk/snippet) or plugincode (plugin)'),
        ];
    }
}
