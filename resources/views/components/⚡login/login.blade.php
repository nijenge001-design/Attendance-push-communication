<div class="min-h-screen bg-gradient-to-br from-primary-500 to-primary-800 flex items-center justify-center px-4 py-8">
    <div
        class="w-full max-w-7xl mx-auto bg-white shadow-2xl rounded-xl overflow-hidden grid grid-cols-1 md:grid-cols-3">
        {{-- Left Side: Login Form --}}
        <div class="bg-white max-w-2xl p-12 flex flex-col justify-center md:col-span-1">
            <div class="text-center mb-6">
                <img
                    src="{{ asset('images/logo_3_1.png') }}"
                    alt="SG Logo"
                    class="h-24 mx-auto mb-6 object-contain"
                >
                <h2 class="text-3xl font-bold text-primary-900">
                    {{--                    Welcome Back--}}
                    Welcome
                </h2>
                <p class="text-gray-600 mt-2">
                    Sign in to access your account
                </p>
            </div>

            <form
                wire:submit="submit"
                class="space-y-6"
                novalidate
            >
                @csrf
                {{-- Error Handling --}}
                @if (session('error'))
                    <div id="alert-border-2" class="flex items-center p-4 mb-4 text-red-800 border-t-4 border-red-300 bg-red-50 dark:text-red-400 dark:bg-gray-800 dark:border-red-800" role="alert">
                        <svg class="shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                        </svg>
                        <div class="ms-3 text-sm font-medium">
                            {{ session('error') }}
                        </div>
                    </div>
                @endif

                {{-- Input Fields --}}
                <div class="space-y-4">
                    <flux:input
                        wire:model.live="form.username"
                        label="Username or Email"
                        placeholder="Enter your username or email"
                        type="text"
                        required
                        aria-required="true"
                        autocomplete="username"
                        :error="$errors->first('username')"
                        class="w-full"
                    />

                    <flux:input
                        wire:model.live="form.password"
                        type="password"
                        label="Password"
                        placeholder="Enter your password"
                        required
                        aria-required="true"
                        autocomplete="current-password"
                        :error="$errors->first('password')"
                        class="w-full"
                        viewable
                    />

                    <div class="flex items-center justify-between">
                        <flux:checkbox wire:model.live="form.rememberMe" label="Remember Me"/>

                        <div class="text-sm">
                            <a
                                href="#"
                                class="font-medium text-primary-600 hover:text-primary-500"
                            >
                                Forgot password?
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="mt-6">
                    <flux:button type="submit" variant="primary" class="w-full">
                        Signing In
                    </flux:button>

                </div>

                {{-- Support Links --}}
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Need help?
                        <a
                            href="#"
                            class="font-medium text-primary-600 hover:text-primary-500"
                        >
                            Contact Support team
                        </a>
                    </p>
                </div>
            </form>
        </div>


        {{-- Right Side: Features Section --}}
        <div
            class="hidden md:block bg-cover bg-center relative md:col-span-2"
            style="background-image: url('{{ asset('images/bg001.png') }}')"
        >
            <div class="absolute inset-0 bg-primary-900/70"></div>
            <div class="relative z-10 flex flex-col justify-center h-full p-12 text-white">
                <h2 class="text-4xl font-bold mb-6">
                    Casual- HRMS
                    Features
                </h2>
                <p class="text-xl mb-8 leading-relaxed">
                    Our HRMS empowers organizations to streamline HR operations across multiple projects with a unified
                    and efficient platform.
                </p>

                <ul class="grid grid-cols-1 gap-2 mb-8">
                    <li class="flex items-center bg-white/10 p-4 rounded-lg transition-all duration-300 hover:bg-opacity-20 hover:shadow-md">
                        <div class="bg-green-100 text-green-600 rounded-full p-3 mr-4 shadow-md">
                            <flux:icon.home-modern variant="solid"/>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-lg">Multi-Project Support</h3>
                            <p class="text-white/80 text-sm">
                                Manage HR operations for multiple projects from a single platform.
                            </p>
                        </div>
                    </li>
                    <li class="flex items-center bg-white/10 p-4 rounded-lg transition-all duration-300 hover:bg-opacity-20 hover:shadow-md">
                        <div class="bg-blue-100 text-blue-600 rounded-full p-3 mr-4 shadow-md">
                            <flux:icon.user-circle variant="solid"/>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-lg">Employee Self-Service Portal</h3>
                            <p class="text-white/80 text-sm">
                                Allow employees to access and manage their own HR information effortlessly.
                            </p>
                        </div>
                    </li>
                    <li class="flex items-center bg-white/10 p-4 rounded-lg transition-all duration-300 hover:bg-opacity-20 hover:shadow-md">
                        <div class="bg-purple-100 text-purple-600 rounded-full p-3 mr-4 shadow-md">
                            <flux:icon.calculator variant="solid"/>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-lg">Payroll Automation</h3>
                            <p class="text-white/80 text-sm">
                                Automate payroll processes, ensuring accuracy and compliance.
                            </p>
                        </div>
                    </li>
                    <li class="flex items-center bg-white/10 p-4 rounded-lg transition-all duration-300 hover:bg-opacity-20 hover:shadow-md">
                        <div class="bg-teal-100 text-teal-600 rounded-full p-3 mr-4 shadow-md">
                            <flux:icon.clock variant="solid"/>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-lg">Advanced Attendance Management</h3>
                            <p class="text-white/80 text-sm">
                                Track and manage attendance in real-time with advanced tools.
                            </p>
                        </div>
                    </li>
                    <li class="flex items-center bg-white/10 p-4 rounded-lg transition-all duration-300 hover:bg-opacity-20 hover:shadow-md">
                        <div class="bg-orange-100 text-orange-600 rounded-full p-3 mr-4 shadow-md">
                            <flux:icon.calendar-date-range variant="solid"/>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold text-lg">Leave Management</h3>
                            <p class="text-white/80 text-sm">
                                Streamline leave requests and approvals with customizable workflows.
                            </p>
                        </div>
                    </li>
                </ul>

            </div>
        </div>

    </div>
</div>
