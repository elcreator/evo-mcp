<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Elements;

use EvolutionCMS\eMCP\Support\ElementTypes;
use EvolutionCMS\eMCP\Tools\Write\BaseWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.elements.get')]
#[Description('Read one template, TV, chunk, snippet or plugin including its code, by id or name.')]
class ElementsGetTool extends BaseWriteTool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $spec = ElementTypes::get((string)$validated['type']);
        $this->requirePermission($spec['permissions']['view']);

        $row = self::find($spec, isset($validated['id']) ? (int)$validated['id'] : null, $validated['name'] ?? null);
        if ($row === null) {
            return $this->respondItem(null);
        }

        $item = [];
        foreach ($spec['read'] as $field) {
            $item[$field] = $row->getAttribute($field);
        }

        $lock = evo()->elementIsLocked((int)$spec['lock_type'], (int)$row->getKey(), true);
        $item['locked_by'] = is_array($lock) ? (string)($lock['username'] ?? '') : null;

        return $this->respondItem($item);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public static function find(array $spec, ?int $id, ?string $name): ?Model
    {
        /** @var class-string<Model> $model */
        $model = $spec['model'];

        if ($id !== null) {
            return $model::query()->find($id);
        }

        $name = trim((string)$name);
        if ($name === '') {
            throw ValidationException::withMessages(['id' => 'Pass id or name.']);
        }

        return $model::query()->where($spec['name_field'], $name)->first();
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(ElementTypes::names())->required(),
            'id' => $schema->integer()->min(1),
            'name' => $schema->string()->description('Exact element name, used when id is omitted'),
        ];
    }
}
