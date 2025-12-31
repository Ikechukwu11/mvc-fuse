<?php
namespace App\Fuse;

use Engine\Fuse\Component;

class TodoList extends Component
{
    public array $todos = [];
    public string $newTodo = '';

    public function mount()
    {
        // Initial data could be fetched here if not already hydrated
        if (empty($this->todos)) {
            $this->todos = [
                ['id' => 1, 'text' => 'Learn Fuse', 'done' => true],
                ['id' => 2, 'text' => 'Build a cool app', 'done' => false],
            ];
        }
    }

    public function add()
    {
        if (trim($this->newTodo) === '') return;
        
        $this->todos[] = [
            'id' => count($this->todos) + 1,
            'text' => $this->newTodo,
            'done' => false
        ];
        $this->newTodo = '';
    }

    public function toggle($id)
    {
        // $id comes as string from frontend usually
        foreach ($this->todos as &$todo) {
            if ($todo['id'] == $id) {
                $todo['done'] = !$todo['done'];
                break;
            }
        }
    }

    public function delete($id)
    {
        $this->todos = array_filter($this->todos, fn($t) => $t['id'] != $id);
    }

    public function render()
    {
        $listHtml = '';
        foreach ($this->todos as $todo) {
            $style = $todo['done'] ? 'text-decoration: line-through; color: #888;' : '';
            $text = htmlspecialchars($todo['text']);
            $listHtml .= <<<HTML
            <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                <span style="$style" fuse:click="toggle({$todo['id']})" style="cursor: pointer;">
                    {$text}
                </span>
                <button fuse:click="delete({$todo['id']})" style="color: red; border: none; background: none; cursor: pointer;">&times;</button>
            </li>
HTML;
        }

        return <<<HTML
        <div style="max-width: 400px; margin: 20px auto; font-family: sans-serif;">
            <h3>Todo List</h3>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <input type="text" fuse:model="newTodo" placeholder="New todo..." style="flex: 1; padding: 8px;">
                <button fuse:click="add" style="padding: 8px 16px; background: #4F46E5; color: white; border: none; border-radius: 4px; cursor: pointer;">Add</button>
            </div>
            <ul style="list-style: none; padding: 0;">
                $listHtml
            </ul>
            <p style="font-size: 0.8em; color: #666; margin-top: 10px;">
                Total: {$this->count()}
            </p>
        </div>
HTML;
    }

    public function count()
    {
        return count($this->todos);
    }
}
