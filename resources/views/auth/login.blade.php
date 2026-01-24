<x-guest-layout>

    <div class=" flex items-center justify-center bg-gray-100 ">
        <div class="w-full  bg-white rounded-xl p-4">

            <!-- Logo / Title -->
            <div class="text-center ">
                <h1 class="text-2xl font-semibold text-gray-800">Welcome Back</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Please sign in to your account
                </p>
            </div>

            <!-- Session Status -->
            <x-auth-session-status class="mb-4 text-sm" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <!-- Email -->
                <div>
                    <x-input-label for="email" :value="__('Email Address')" />
                    <x-text-input
                        id="email"
                        class="block mt-1 w-full rounded-lg focus:ring-2 focus:ring-indigo-500"
                        type="email"
                        name="email"
                        :value="old('email')"
                        required
                        autofocus
                        autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1 text-sm" />
                </div>

                <!-- Password -->
                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input
                        id="password"
                        class="block mt-1 w-full rounded-lg focus:ring-2 focus:ring-indigo-500"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1 text-sm" />
                </div>

                <!-- Remember + Forgot -->
                <div class="flex items-center justify-between">
                    <label for="remember_me" class="inline-flex items-center text-sm text-gray-600">
                        <input
                            id="remember_me"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            name="remember">
                        <span class="ml-2">Remember me</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-sm text-indigo-600 hover:text-indigo-800">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <!-- Login Button -->
                <x-primary-button class="w-full justify-center py-3 text-base">
                    {{ __('Log in') }}
                </x-primary-button>
            </form>

            <!-- Footer -->
            <div class="mt-6 text-center text-sm text-gray-500">
                © {{ date('Y') }} BookMe. All rights reserved.
            </div>
        </div>
    </div>

</x-guest-layout>
