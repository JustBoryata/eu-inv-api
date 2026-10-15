<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ListClientsRequest;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Plain CRUD with almost no business rules, so no Action classes: the tenant
 * scope on Client already keeps every query inside the user's organisation.
 */
class ClientController extends Controller
{
    /**
     * List clients
     */
    public function index(ListClientsRequest $request): AnonymousResourceCollection
    {
        $clients = Client::query()
            ->when($request->search(), function (Builder $query, string $search): void {
                // Escape LIKE wildcards so a search for "50%" means the literal text.
                $query->whereLike('name', '%'.addcslashes($search, '%_\\').'%');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    /**
     * Create a client
     */
    public function store(StoreClientRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $client = new Client($request->validated());
        $client->organisation_id = $user->organisation_id;
        $client->save();

        return (new ClientResource($client))->response()->setStatusCode(201);
    }

    /**
     * Show a client
     */
    public function show(Client $client): ClientResource
    {
        return new ClientResource($client);
    }

    /**
     * Update a client
     */
    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client);
    }

    /**
     * Delete a client
     *
     * A client that has invoices cannot be deleted (409).
     */
    public function destroy(Client $client): Response
    {
        // The foreign key would refuse anyway, but with a 500. Say why instead.
        if ($client->invoices()->exists()) {
            throw new ConflictHttpException('This client has invoices, so it can\'t be deleted.');
        }

        $client->delete();

        return response()->noContent();
    }
}
