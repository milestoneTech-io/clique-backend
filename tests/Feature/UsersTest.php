<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UsersTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_returns_a_user_as_a_resource_object()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/1', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json'
        ])
            ->assertStatus(200)
            ->assertJson([
                "data" => [
                    "id" => $user->id,
                    "type" => "users",
                    "attributes" => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'username' => $user->username,
                        'role' => $user->role,
                        'profile_avatar' => $user->profile_avatar,
                        'status' => $user->status,
                        'created_at' => $user->created_at->toJSON(),
                        'updated_at' => $user->updated_at->toJSON()
                    ]
                ]
            ]);
    }

    public function test_It_returns_all_users_as_a_collection_of_resource_objects()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->get('/api/v1/users', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json'
        ])->assertStatus(200);
    }

    public function test_It_can_paginate_users_through_a_page_query_parameter()
    {
        $users = User::factory(9)->create();
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->get('/api/v1/users?page[size]=5&page[number]=1', [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json'
        ])->assertStatus(200)->assertJson([
            "data" => [
                [
                    "id" => $user->id,
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[0]->name,
                        'created_at' => $users[0]->created_at->toJSON(),
                        'updated_at' => $users[0]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => $user->id,
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[1]->name,
                        'created_at' => $users[1]->created_at->toJSON(),
                        'updated_at' => $users[1]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => $user->id,
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[2]->name,
                        'created_at' => $users[2]->created_at->toJSON(), 
                        'updated_at' => $users[2]->updated_at->toJSON(),
                    ]
                ],
                [
                    "id" => $user->id,
                    "type" => "users",
                    "attributes" => [
                        'name' => $users[3]->name,
                        'created_at' => $users[3]->created_at->toJSON(),
                        'updated_at' => $users[3]->updated_at->toJSON(),
                    ]
                ],
            ],
            'links' => [
                'first' => route('users.index', ['page[size]' => 5, 'page[number]' => 1]),
                'last' => route('users.index', ['page[size]' => 5, 'page[number]' => 2]),
                'prev' => null,
                'next' => route('users.index', ['page[size]' => 5, 'page[number]' => 2]),
            ]
        ]);
    }

    public function test_it_validates_that_the_attributes_member_is_an_object_given_when_updating_a_task()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/tasks/1', [
            'data' => [
                'id' => '1',
                'type' => 'tasks',
                'attributes' => 'not an object',

            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json'
        ])->assertStatus(422)->assertJson([
            'errors' => [
                [
                    'title' => 'Validation Error',
                    'details' => 'The data.attributes must be an array.',
                    'source' => [
                        'pointer' => '/data/attributes',
                    ]
                ]
            ]
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => 1,
            'title' => $task->title
        ]);
    }

    public function test_it_can_update_a_user_from_a_resource_object()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Storage::fake('avatars');
 
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->patchJson('/api/v1/users/1', [
            'data' => [
                'id' => '1',
                'type' => 'users',
                'attributes' => [
                    'name' => 'Jane Doe',
                    'email' => 'janet@doe.com',
                    // 'username' => 'janet',
                    'status' => 1,
                    'profile_avatar' => $file
                ]
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json',
            'enctype' => 'multipart/form-data'
        ])->assertStatus(200);
        

        // Storage::disk('avatars')->assertExists($file->hashName());
        
    }


    public function test_it_can_create_a_user_from_a_resource_object()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/register', [
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'name' => 'John Doe',
                    'email' => "john@doe.com",
                    'password' => "secret",
                    'password_confirmation' => "secret"
                ]
            ]
        ], [
            'accept' => 'application/vnd.api+json',
            'content-type' => 'application/vnd.api+json'
        ])->assertStatus(200);

        
    }

    public function test_it_can_delete_a_user_through_a_delete_request()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->delete('/api/v1/users/1', [], [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
        ])->assertStatus(204);

        $this->assertDatabaseMissing('users', [
            'id' => 1,
            'name' => $user->title,
        ]);
    }
    


    // public function test_it_can_register_a_user()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->postJson('/api/auth/register', [
           
    //             // 'attributes' => [
    //                 'name' => 'John Doe',
    //                 'email' => 'john@doe.com',
    //                 'password' => 'Secret',
    //                 'password_confirmation' => 'Secret',
    //             // ]
            
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(200)
    //         ->assertJson([
    //             [
    //                 "status" => "Success",
    //                 "message" => null,
    //                 "data" => [
    //                     "token" => "1|GT9SjpiPSeahRQnzwC2LaY0YeBbEe7HyFYA7SRV3"
    //                 ]
    //             ]
    //         ]);

    //     $this->assertDatabaseHas('tasks', [
    //         'id' => 1,
    //         'name' => 'John Doe',
    //     ]);
    // }

    // public function test_it_validates_that_the_type_member_is_given_when_creating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->postJson('/api/v1/tasks', [
    //         'data' => [
    //             'type' => '',
    //             'attributes' => [
    //                 'title' => 'John Doe',
    //             ]
    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.type field is required.',
    //                 'source' => [
    //                     'pointer' => '/data/type',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseMissing('tasks', [
    //         'id' => 1,
    //         'title' => 'John Doe'
    //     ]);
    // }

    // public function test_it_validates_that_the_type_member_is_given_when_updating_a_task()
    // {
    //     $user = User::factory()->create();
    //     $task = Task::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->patchJson('/api/v1/tasks/1', [
    //         'data' => [
    //             'id' => '1',
    //             'type' => '',
    //             'attributes' => [
    //                 'title' => 'John Doe',
    //             ]
    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.type field is required.',
    //                 'source' => [
    //                     'pointer' => '/data/type',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseHas('tasks', [
    //         'id' => 1,
    //         'title' => $task->title
    //     ]);
    // }

    // public function test_it_validates_that_the_type_member_has_the_value_of_tasks_when_creating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->postJson('/api/v1/tasks', [
    //         'data' => [
    //             'type' => 'task',
    //             'attributes' => [
    //                 'title' => 'John Doe',
    //             ]
    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The selected data.type is invalid.',
    //                 'source' => [
    //                     'pointer' => '/data/type',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseMissing('tasks', [
    //         'id' => 1,
    //         'title' => 'John Doe'
    //     ]);
    // }

    // public function test_it_validates_that_the_type_member_has_the_value_of_tasks_when_updating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->patchJson('/api/v1/users/1', [
    //         'data' => [
    //             'id' => '1',
    //             'type' => 'users',
    //             'attributes' => [
    //                 'name' => 'John Doe',
    //             ]
    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The selected data.type is invalid.',
    //                 'source' => [
    //                     'pointer' => '/data/type',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseHas('tasks', [
    //         'id' => 1,
    //         'title' => $task->title
    //     ]);
    // }

    // public function test_it_validates_that_a_title_attribute_has_been_given_when_creating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->postJson('/api/v1/tasks', [
    //         'data' => [
    //             'type' => 'tasks',
    //             'attributes' => [
    //                 'title' => '',
    //             ],

    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.attributes.title field is required.',
    //                 'source' => [
    //                     'pointer' => '/data/attributes/title',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseMissing('tasks', [
    //         'id' => 1,
    //         'title' => 'John Doe'
    //     ]);
    // }

    // public function test_it_validates_that_the_attributes_member_has_been_given_when_updating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);
    //     $task = Task::factory()->create();

    //     $this->patchJson('/api/v1/tasks/1', [
    //         'data' => [
    //             'id' => '1',
    //             'type' => 'tasks',

    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.attributes field is required.',
    //                 'source' => [
    //                     'pointer' => '/data/attributes',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseHas('tasks', [
    //         'id' => 1,
    //         'title' => $task->title
    //     ]);
    // }

    // public function test_it_validates_that_a_title_attribute_is_a_string_when_creating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->postJson('/api/v1/tasks', [
    //         'data' => [
    //             'type' => 'tasks',
    //             'attributes' => [
    //                 'title' => 47,
    //             ],

    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.attributes.title must be a string.',
    //                 'source' => [
    //                     'pointer' => '/data/attributes/title',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseMissing('tasks', [
    //         'id' => 1,
    //         'title' => 'John Doe'
    //     ]);
    // }

    // public function test_it_validates_that_a_title_attribute_is_a_string_when_updating_a_task()
    // {
    //     $user = User::factory()->create();
    //     $task = Task::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->patchJson('/api/v1/tasks/1', [
    //         'data' => [
    //             'id' =>  '1',
    //             'type' => 'tasks',
    //             'attributes' => [
    //                 'title' => 47,
    //             ],

    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.attributes.title must be a string.',
    //                 'source' => [
    //                     'pointer' => '/data/attributes/title',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseHas('tasks', [
    //         'id' => 1,
    //         'title' => $task->title
    //     ]);
    // }

    // public function test_it_validates_that_an_id_member_is_a_string_when_updating_a_task()
    // {
    //     $user = User::factory()->create();
    //     $task = Task::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->patchJson('/api/v1/tasks/1', [
    //         'data' => [
    //             'id' => 1,
    //             'type' => 'tasks',
    //             'attributes' => [
    //                 'title' => 'Jane Doe',
    //             ]

    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.id must be a string.',
    //                 'source' => [
    //                     'pointer' => '/data/id',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseHas('tasks', [
    //         'id' => 1,
    //         'title' => $task->title
    //     ]);
    // }
    
    // public function test_it_validates_that_the_attributes_member_has_been_given_when_creating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->postJson('/api/v1/tasks', [
    //         'data' => [
    //             'type' => 'tasks'
    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.attributes field is required.',
    //                 'source' => [
    //                     'pointer' => '/data/attributes',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseMissing('tasks', [
    //         'id' => 1,
    //         'title' => 'John Doe'
    //     ]);
    // }

    // public function test_it_validates_that_the_attributes_member_is_an_object_given_when_creating_a_task()
    // {
    //     $user = User::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->postJson('/api/v1/tasks', [
    //         'data' => [
    //             'type' => 'tasks',
    //             'attributes' => 'not an object'

    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)->assertJson([
    //         'errors' => [
    //             [
    //                 'title' => 'Validation Error',
    //                 'details' => 'The data.attributes must be an array.',
    //                 'source' => [
    //                     'pointer' => '/data/attributes',
    //                 ]
    //             ]
    //         ]
    //     ]);

    //     $this->assertDatabaseMissing('tasks', [
    //         'id' => 1,
    //         'title' => 'John Doe'
    //     ]);
    // }

    // public function test_it_validates_that_an_id_member_is_given_when_updating_a_task()
    // {
    //     $user = User::factory()->create();
    //     $task = Task::factory()->create();
    //     Sanctum::actingAs($user);

    //     $this->patchJson('/api/v1/tasks/1', [
    //         'data' => [
    //             'type' => 'tasks',
    //             'attributes' => [
    //                 'title' => 'Jane Doe',
    //             ]
    //         ]
    //     ], [
    //         'accept' => 'application/vnd.api+json',
    //         'content-type' => 'application/vnd.api+json'
    //     ])->assertStatus(422)
    //         ->assertJson([
    //             'errors' => [
    //                 [
    //                     'title' => 'Validation Error',
    //                     'details' => 'The data.id field is required.',
    //                     'source' => [
    //                         'pointer' => '/data/id',
    //                     ]
    //                 ]
    //             ]
    //         ]);
    //     $this->assertDatabaseHas('tasks', [
    //         'id' => 1,
    //         'title' => $task->title,
    //     ]);
    // }

    
}
