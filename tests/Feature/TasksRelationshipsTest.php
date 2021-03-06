<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class TasksRelationshipsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_returns_a_relationship_to_users_adhering_to_json_api_spec()
    {
        $auth = User::factory()->create();
        $users = User::factory(2)->create();
        $project = Project::factory()->create(['user_id' => $auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->assignees()->sync($users->pluck('id'));
        Sanctum::actingAs($auth);
        $this->getJson('/api/v1/tasks/1?assignees', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => '1',
                    'type' => 'tasks',
                    'attributes' => [
                        'title' => $task->title,
                        'project_id' => $project->id
                    ],
                    'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $task->id),
                                'related' => route('tasks.users', $task->id),
                            ],
                            'data' => [
                                [
                                    'id' => $users[0]->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => $users[1]->id,
                                    'type' => 'users'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
    }

    public function test_a_relationship_link_to_users_returns_all_related_users_as_resource_id_ob()
    {
        $auth = User::factory()->create();
        $users = User::factory(3)->create();
        $project = Project::factory()->create(['user_id' => $auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->assignees()->sync($users->pluck('id'));
        Sanctum::actingAs($auth);
        $this->getJson('/api/v1/tasks/1/relationships/users', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'id' => '2',
                        'type' => 'users',
                    ],
                    [
                        'id' => '3',
                        'type' => 'users',
                    ],
                    [
                        'id' => '4',
                        'type' => 'users',
                    ],
                ]
            ]);
    }

    public function test_project_creator_can_modify_relationships_to_users_and_add_new_relationships()
    {
        $users = User::factory(10)->create();
        $auth = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->assignees()->sync($users->pluck('id'));
        Sanctum::actingAs($auth);
        $this->patchJson('/api/v1/tasks/1/relationships/users', [
            'data' => [
                [
                    'id' => '5',
                    'type' => 'users',
                ],
                [
                    'id' => '6',
                    'type' => 'users',
                ]
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(204);
        $this->assertDatabaseHas('task_user', [
            'user_id' => 5,
            'task_id' => 1,
        ])->assertDatabaseHas('task_user', [
            'user_id' => 6,
            'task_id' => 1,
        ]);
    }

    public function test_it_can_modify_relationships_to_users_and_remove_relationships()
    {
        $users = User::factory(10)->create();
        $auth = User::factory()->create();
        $project = Project::factory()->create(['user_id' =>$auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->assignees()->sync($users->pluck('id'));
        Sanctum::actingAs($auth);
        $this->patchJson('/api/v1/tasks/1/relationships/users', [
            'data' => [
                [
                    'id' => '1',
                    'type' => 'users',
                ],
                [
                    'id' => '2',
                    'type' => 'users',
                ],
                [
                    'id' => '5',
                    'type' => 'users',
                ],
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(204);
        $this->assertDatabaseHas('task_user', [
            'user_id' => 1,
            'task_id' => 1,
        ])->assertDatabaseHas('task_user', [
            'user_id' => 2,
            'task_id' => 1,
        ])->assertDatabaseHas('task_user', [
            'user_id' => 5,
            'task_id' => 1,
        ])->assertDatabaseMissing('task_user', [
            'user_id' => 3,
            'task_id' => 1,
        ])->assertDatabaseMissing('task_user', [
            'user_id' => 4,
            'task_id' => 1,
        ]);
    }

    public function test_it_can_remove_all_relationships_to_users_with_an_empty_collection()
    {
        $users = User::factory(10)->create();
        $auth = User::factory()->create();
        $project = Project::factory()->create(['user_id'=> $auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->assignees()->sync($users->pluck('id'));
        Sanctum::actingAs($auth);
        $this->patchJson('/api/v1/tasks/1/relationships/users', [
            'data' => []
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(204);
        $this->assertDatabaseMissing('task_user', [
            'user_id' => 1,
            'task_id' => 1,
        ])->assertDatabaseMissing('task_user', [
            'user_id' => 2,
            'task_id' => 1,
        ])->assertDatabaseMissing('task_user', [
            'user_id' => 3,
            'task_id' => 1,
        ]);
    }

    public function test_it_returns_a_404_not_found_when_trying_to_add_relationship_to_a_non_existing()
    {
        $users = User::factory(2)->create();
        $auth = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        Sanctum::actingAs($auth);
        $this->patchJson('/api/v1/tasks/1/relationships/users', [
            'data' => [
                [
                    'id' => '5',
                    'type' => 'users',
                ],
                [
                    'id' => '6',
                    'type' => 'users',
                ]
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(404)->assertJson([
            'errors' => [
                [
                    'title' => 'Not Found Http Exception',
                    'details' => 'Resource not found',
                ]
            ]
        ]);
    }

    public function test_it_validates_that_the_id_member_is_given_when_updating_a_relationship()
    {
        $users = User::factory(5)->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $auth = User::factory()->create();
        Sanctum::actingAs($auth);
        $this->patchJson('/api/v1/tasks/1/relationships/users', [
            'data' => [
                [
                    'type' => 'users',
                ],
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(422)->assertJson([
            'errors' => [
                [
                    'title' => 'Validation Error',
                    'details' => 'The data.0.id field is required.',
                    'source' => [
                        'pointer' => '/data/0/id',
                    ]
                ]
            ]
        ]);
    }

    public function test_it_validates_that_the_id_member_is_a_string_when_updating_a_relationship()
    {
        $users = User::factory(5)->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $auth = User::factory()->create();
        Sanctum::actingAs($auth);
        $this->patchJson('/api/v1/tasks/1/relationships/users', [
            'data' => [
                [
                    'id' => 5,
                    'type' => 'users',
                ],
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(422)->assertJson([
            'errors' => [
                [
                    'title' => 'Validation Error',
                    'details' => 'The data.0.id must be a string.',
                    'source' => [
                        'pointer' => '/data/0/id',
                    ]
                ]
            ]
        ]);
    }

    public function test_it_validates_that_the_type_member_is_given_when_updating_a_relationship()
    {
        $users = User::factory(5)->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $auth = User::factory()->create();
        Sanctum::actingAs($auth);
        $this->patchJson('/api/v1/tasks/1/relationships/users', [
            'data' => [
                [
                    'id' => '5',
                ],
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(422)->assertJson([
            'errors' => [
                [
                    'title' => 'Validation Error',
                    'details' => 'The data.0.type field is required.',
                    'source' => [
                        'pointer' => '/data/0/type',
                    ]
                ]
            ]
        ]);
    }


    public function test_it_can_get_all_related_users_as_resource_objects_from_related_link()
    {
        $auth = User::factory()->create();
        $users = User::factory(3)->create();
        $project = Project::factory()->create(['user_id' => $auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->assignees()->sync($users->pluck('id'));
        Sanctum::actingAs($auth);
        $this->getJson('/api/v1/tasks/1/relationships/users', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(200);
    }

    public function test_it_includes_related_resource_objects_when_an_include_query_param_is_given()
    {
        $users = User::factory(3)->create();
        $auth = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $auth->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);
        $task->assignees()->sync($users->pluck('id'));
        Sanctum::actingAs($auth);
        $this->getJson('/api/v1/tasks/1?include=assignees', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => '1',
                    'type' => 'tasks',
                    'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $project->id),
                                'related' => route('tasks.users', $project->id),
                            ],
                            'data' => [
                                [
                                    'id' => (string)$users->get(0)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(1)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(2)->id,
                                    'type' => 'users'
                                ]
                            ]
                        ]
                    ]
                ],
                'included' => [
                    [
                        "id" => '1',
                        "type" => "users",
                        "attributes" => [
                            'name' => $users[0]->name,
                            'created_at' => $users[0]->created_at->toJSON(),
                            'updated_at' => $users[0]->updated_at->toJSON(),
                        ]
                    ],
                    [
                        "id" => '2',
                        "type" => "users",
                        "attributes" => [
                            'name' => $users[1]->name,
                            'created_at' => $users[1]->created_at->toJSON(),
                            'updated_at' => $users[1]->updated_at->toJSON(),
                        ]
                    ],
                    [
                        "id" => '3',
                        "type" => "users",
                        "attributes" => [
                            'name' => $users[2]->name,
                            'created_at' => $users[2]->created_at->toJSON(),
                            'updated_at' => $users[2]->updated_at->toJSON(),
                        ]
                    ],
                ]
            ]);
    }

    public function test_it_does_not_include_related_resource_objects_when_an_include_query_param_is_not_given()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/tasks/1', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])
            ->assertStatus(200)
            ->assertJsonMissing([
                'included' => [],
            ]);
    }

    public function test_it_includes_related_resource_objects_for_a_collection_when_an_include_query_param_is_given()
    {
        $projects = Project::factory()->create();
        $tasks = Task::factory(3)->create();
        $users = User::factory(3)->create();
        
        $tasks->each(function ($task, $key) use ($users) {
            if ($key === 0) {
                $task->assignees()->attach($users->pluck('id'));
            }
        });
        $auth = User::factory()->create();
        Sanctum::actingAs($auth);
        
        $this->get('/api/v1/tasks?include=assignees', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(200)->assertJson([
            "data" => [
                [
                    "id" => '1',
                    "type" => "tasks",
                    "attributes" => [
                        'title' => $tasks[0]->title,
                        'created_at' => $tasks[0]->created_at->toJSON(),
                        'updated_at' => $tasks[0]->updated_at->toJSON(),
                    ],
                    'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $tasks[0]->id),
                                'related' => route('tasks.users', $tasks[0]->id),
                            ],
                            'data' => [
                                [
                                    'id' => (string)$users->get(0)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(1)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(2)->id,
                                    'type' => 'users'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "id" => '2',
                    "type" => "tasks",
                    "attributes" => [
                        'title' => $tasks[1]->title,
                        'created_at' => $tasks[1]->created_at->toJSON(),
                        'updated_at' => $tasks[1]->updated_at->toJSON(),
                    ],
                    'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $tasks[1]->id),
                                'related' => route('tasks.users', $tasks[1]->id),
                            ],
                        ]
                    ]
                ],
                [
                    "id" => '3',
                    "type" => "tasks",
                    "attributes" => [
                        'title' => $tasks[2]->title,
                        'created_at' => $tasks[2]->created_at->toJSON(),
                        'updated_at' => $tasks[2]->updated_at->toJSON(),
                    ],
                    'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $tasks[2]->id),
                                'related' => route('tasks.users', $tasks[2]->id),
                            ],
                        ]
                    ]
                ],
            ],
            'included' => [
                [
                    "id" => '1',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[0]->name,
                        'created_at' => $users[0]->created_at->toJSON(),
                        'updated_at' => $users[0]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '2',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[1]->name,
                        'created_at' => $users[1]->created_at->toJSON(),
                        'updated_at' => $users[1]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '3',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[2]->name,
                        'created_at' => $users[2]->created_at->toJSON(),
                        'updated_at' => $users[2]->updated_at->toJSON(),
                    ]
                ],
            ]
        ]);
    }

    public function test_it_does_not_include_related_resource_objects_for_a_collection_when_an_include_param_is_not_given()
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->get('/api/v1/tasks', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(200)
            ->assertJsonMissing([
                'included' => [],
            ]);
    }

    public function test_it_only_includes_a_related_resource_object_once_for_a_collection()
    {
        $users = User::factory(3)->create();
        $auth = User::factory()->create();
        $project = Project::factory()->create();
        $tasks = Task::factory(3)->create();
        $tasks->each(function ($task) use ($users) {
            $task->assignees()->attach($users->pluck('id'));
        });
        Sanctum::actingAs($auth);
        
        $this->get('/api/v1/tasks?include=assignees', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
        ])->assertStatus(200)->assertJson([
            "data" => [
                [
                    "id" => '1',
                    "type" => "tasks",
                    "attributes" => [
                        'title' => $tasks[0]->title,
                        'created_at' => $tasks[0]->created_at->toJSON(),
                        'updated_at' => $tasks[0]->updated_at->toJSON(),
                    ],
                    'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $tasks[0]->id), 
                                'related' => route('tasks.users', $tasks[0]->id),
                            ],
                            'data' => [
                                [
                                    'id' => (string)$users->get(0)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(1)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(2)->id,
                                    'type' => 'users'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "id" => '2',
                    "type" => "tasks",
                    "attributes" => [
                        'title' => $tasks[1]->title,
                        'created_at' => $tasks[1]->created_at->toJSON(),
                        'updated_at' => $tasks[1]->updated_at->toJSON(),
                    ], 'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $tasks[1]->id),
                                'related' => route('tasks.users', $tasks[1]->id),
                            ],
                            'data' => [
                                [
                                    'id' => (string)$users->get(0)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(1)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(2)->id,
                                    'type' => 'users'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "id" => '3',
                    "type" => "tasks",
                    "attributes" => [
                        'title' => $tasks[2]->title,
                        'created_at' => $tasks[2]->created_at->toJSON(),
                        'updated_at' => $tasks[2]->updated_at->toJSON(),
                    ],
                    'relationships' => [
                        'users' => [
                            'links' => [
                                'self' => route('tasks.relationships.users', $tasks[2]->id),
                                'related' => route('tasks.users', $tasks[2]->id),
                            ],
                            'data' => [
                                [
                                    'id' => (string)$users->get(0)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(1)->id,
                                    'type' => 'users'
                                ],
                                [
                                    'id' => (string)$users->get(2)->id,
                                    'type' => 'users'
                                ]
                            ]
                        ]
                    ]
                ],
            ],
            'included' => [
                [
                    "id" => '1',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[0]->name,
                        'created_at' => $users[0]->created_at->toJSON(),
                        'updated_at' => $users[0]->updated_at->toJSON(),
                    ]
                ], [
                    "id" => '2',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[1]->name,
                        'created_at' => $users[1]->created_at->toJSON(),
                        'updated_at' => $users[1]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '3',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[2]->name,
                        'created_at' => $users[2]->created_at->toJSON(),
                        'updated_at' => $users[2]->updated_at->toJSON(),
                    ]
                ],
            ]
        ])->assertJsonMissing([
            'included' => [
                [
                    "id" => '1',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[0]->name,
                        'created_at' => $users[0]->created_at->toJSON(),
                        'updated_at' => $users[0]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '2',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[1]->name,
                        'created_at' => $users[1]->created_at->toJSON(),
                        'updated_at' => $users[1]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '3',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[2]->name,
                        'created_at' => $users[2]->created_at->toJSON(),
                        'updated_at' => $users[2]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '1',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[0]->name,
                        'created_at' => $users[0]->created_at->toJSON(),
                        'updated_at' => $users[0]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '2',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[1]->name,
                        'created_at' => $users[1]->created_at->toJSON(),
                        'updated_at' => $users[1]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '3',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[2]->name, 
                        'created_at' => $users[2]->created_at->toJSON(),
                        'updated_at' => $users[2]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '1',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[0]->name,
                        'created_at' => $users[0]->created_at->toJSON(),
                        'updated_at' => $users[0]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '2',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[1]->name,
                        'created_at' => $users[1]->created_at->toJSON(),
                        'updated_at' => $users[1]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => '3',
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[2]->name,
                        'created_at' => $users[2]->created_at->toJSON(),
                        'updated_at' => $users[2]->updated_at->toJSON(),
                    ]
                ],
            ]
        ]);
    }
    
}
