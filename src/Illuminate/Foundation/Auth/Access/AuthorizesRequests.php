<?php

namespace Illuminate\Foundation\Auth\Access;

use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\Access\Gate;

trait AuthorizesRequests
{
    /**
     * Authorize a given action for the current user.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        [$ability, $arguments] = $this->parseAbilityAndArguments($ability, $arguments);

        return app(Gate::class)->authorize($ability, $arguments);
    }

    /**
     * Authorize a given action for a user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|mixed  $user
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeForUser($user, $ability, $arguments = [])
    {
        [$ability, $arguments] = $this->parseAbilityAndArguments($ability, $arguments);

        return app(Gate::class)->forUser($user)->authorize($ability, $arguments);
    }

    /**
     * Guesses the ability's name if it wasn't provided.
     *
     * @param  mixed  $ability
     * @param  mixed|array  $arguments
     * @return array
     */
    protected function parseAbilityAndArguments($ability, $arguments)
    {
        if (is_string($ability) && strpos($ability, '\\') === false) {
            return [$ability, $arguments];
        }

        $method = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];

        return [$this->normalizeGuessedAbilityName($method), $ability];
    }

    /**
     * Normalize the ability name that has been guessed from the method name.
     *
     * @param  string  $ability
     * @return string
     */
    protected function normalizeGuessedAbilityName($ability)
    {
        $map = $this->resourceAbilityMap();

        return $map[$ability] ?? $ability;
    }

    /**
     * Authorize a resource action based on the incoming request.
     *
     * @param  string  $model
     * @param  string|null  $parameter
     * @param  array  $options
     * @param  \Illuminate\Http\Request|null  $request
     * @param  array $extendedResourceAbilities
     * @param  array $extendedResourceMethodsWithoutModel
     * @return void
     */
    public function authorizeResource($model, $parameter = null, array $options = [], $request = null, array $extendedResourceAbilities = [], array $extendedResourceMethodsWithoutModel = [])
    {
        $parameter = $parameter ?: Str::snake(class_basename($model));

        $middleware = [];

        foreach ($this->resourceAbilityMap(!empty($extendedResourceAbilities) ? $extendedResourceAbilities : $model::$extendedResourceAbilities) as $method => $ability) {
            $modelName = in_array($method, $this->resourceMethodsWithoutModels(!empty($extendedResourceMethodsWithoutModel) ? $extendedResourceMethodsWithoutModel : $model::$extendedResourceMethodsWithoutModel)) ? $model : $parameter;

            $middleware["can:{$ability},{$modelName}"][] = $method;
        }

        foreach ($middleware as $middlewareName => $methods) {
            $this->middleware($middlewareName, $options)->only($methods);
        }
    }

    /**
     * Get the map of resource methods to ability names.
     * @param array $extendedResourceAbilities
     * @return array
     */
    protected function resourceAbilityMap(array $extendedResourceAbilities)
    {
        return array_merge ( [
                                 'show' => 'view',
                                 'create' => 'create',
                                 'store' => 'create',
                                 'edit' => 'update',
                                 'update' => 'update',
                                 'destroy' => 'delete',
                             ],$extendedResourceAbilities
        );
    }

    /**
     * Get the list of resource methods which do not have model parameters.
     * @param array $extendedResourceMethodsWithoutModel
     * @return array
     */
    protected function resourceMethodsWithoutModels(array $extendedResourceMethodsWithoutModel)
    {
        return array_merge ( ['index', 'create', 'store'],$extendedResourceMethodsWithoutModel);
    }
}
