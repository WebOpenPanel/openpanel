<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - OpenPanel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="h-full bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500">
    <div class="flex items-center justify-center min-h-full py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="text-center mb-8">
                    <div class="mx-auto w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mb-4 shadow-lg">
                        <i class="fas fa-server text-white text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">OpenPanel</h2>
                    <p class="text-sm text-gray-500 mt-1">Control Web Panel</p>
                </div>

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">Username or Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" name="username" id="username" value="{{ old('username') }}" required autofocus
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm @error('username') border-red-500 @enderror"
                                placeholder="Enter username or email">
                        </div>
                        @error('username')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <div class="relative" x-data="{ show: false }">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input :type="show ? 'text' : 'password'" name="password" id="password" required
                                class="block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                placeholder="Enter password">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'" class="text-gray-400 hover:text-gray-600"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" name="remember" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </form>

                <p class="text-center text-xs text-gray-400 mt-6">
                    OpenPanel Control Panel &copy; {{ date('Y') }}
                </p>
            </div>
        </div>
    </div>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
