<?php

use Livewire\Attributes\Renderless;
use Livewire\Component;

new class extends Component {
    public array $nodes = [
        ['id' => '1', 'position' => ['x' => 240, 'y' => 20], 'data' => ['label' => 'CEO', 'title' => 'Chief Executive']],
        ['id' => '2', 'position' => ['x' => 80, 'y' => 180], 'data' => ['label' => 'CTO', 'title' => 'Engineering']],
        ['id' => '3', 'position' => ['x' => 400, 'y' => 180], 'data' => ['label' => 'CFO', 'title' => 'Finance']],
    ];

    public array $edges = [
        ['id' => 'e1-2', 'source' => '1', 'target' => '2', 'markerEnd' => 'arrowclosed'],
        ['id' => 'e1-3', 'source' => '1', 'target' => '3', 'markerEnd' => 'arrowclosed'],
    ];

    public string $lastDrag = '—';

    #[Renderless]
    public function onNodeDragEnd(string $nodeId, array $position = []): void
    {
        $x = (float) ($position['x'] ?? 0);
        $y = (float) ($position['y'] ?? 0);

        foreach ($this->nodes as &$node) {
            if ((string) $node['id'] !== (string) $nodeId) {
                continue;
            }

            $node['position'] = ['x' => $x, 'y' => $y];
            break;
        }
        unset($node);
    }

    private function parseDrag(mixed $payload, mixed $maybeX, mixed $maybeY): array
    {
        if (is_array($payload)) {
            $id = $payload['id'] ?? $payload['nodeId'] ?? $payload['node']['id'] ?? null;
            $x = $payload['x'] ?? $payload['position']['x'] ?? $payload['node']['position']['x'] ?? null;
            $y = $payload['y'] ?? $payload['position']['y'] ?? $payload['node']['position']['y'] ?? null;

            return [$id, $x, $y];
        }

        return [$payload, $maybeX, $maybeY];
    }
};
?>

<div class="space-y-3">
    <p class="text-sm text-gray-500">Last drop: {{ $lastDrag }}</p>

    <div style="height: 720px;">
        <x-flow
            :nodes="$nodes"
            :edges="$edges"
            :controls="true"
            background="dots"
            @node-drag-end="onNodeDragEnd($event.detail)"
        >
            <x-slot:node>
                <x-flow-handle type="target" position="top" />
                <div class="min-w-[140px] rounded-lg border bg-white px-3 py-2 text-center">
                    <div class="text-sm font-semibold" x-text="node.data.label"></div>
                    <div class="text-xs text-gray-500" x-text="node.data.title"></div>
                    <div class="mt-1 font-mono text-[10px] text-gray-400"
                         x-text="Math.round(node.position.x) + ', ' + Math.round(node.position.y)"></div>
                </div>
                <x-flow-handle type="source" position="bottom" />
            </x-slot:node>
        </x-flow>
    </div>
</div>
