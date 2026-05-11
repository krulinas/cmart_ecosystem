<template>
  <div class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow-md py-4 px-6 flex flex-col sm:flex-row justify-between items-center">
      <div class="text-3xl font-extrabold text-red-600 mb-4 sm:mb-0 tracking-tight">
        🎪 Carboot<span class="text-gray-800">@CMart</span>
      </div>
      <div class="space-x-2 sm:space-x-4 flex items-center">
        <router-link to="/" class="text-gray-600 hover:text-red-600 font-semibold transition">Home</router-link>
        <router-link to="/vendor-booking" class="text-gray-600 hover:text-red-600 font-semibold transition">Become a Vendor</router-link>
        <router-link to="/admin" class="bg-gray-800 text-white px-4 py-2 rounded-lg shadow-md hover:bg-gray-700 transition text-sm font-bold">Admin Login</router-link>
      </div>
    </nav>

    <header class="bg-gradient-to-br from-red-500 to-orange-400 text-white text-center py-24 px-6 shadow-inner">
      <h1 class="text-4xl sm:text-6xl font-extrabold mb-6 drop-shadow-md">
        Changlun's Biggest Weekend Market!
      </h1>
      <p class="text-lg sm:text-2xl mb-10 font-medium max-w-2xl mx-auto opacity-90">
        Join the community, find amazing deals, or start your own micro-business this weekend.
      </p>
      <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
        <button class="w-full sm:w-auto bg-white text-red-600 font-bold py-3 px-8 rounded-full shadow-xl hover:bg-gray-100 transition transform hover:-translate-y-1">
          📅 View Event Calendar
        </button>
        <router-link to="/vendor-booking" class="w-full sm:w-auto bg-transparent border-2 border-white text-white font-bold py-3 px-8 rounded-full shadow-lg hover:bg-white hover:text-red-600 transition transform hover:-translate-y-1">
          🏪 Book a Tapak
        </router-link>
      </div>
    </header>

    <main class="py-20 px-6 text-center">
      <h2 class="text-3xl font-extrabold text-gray-800 mb-4">What's Happening at CMART?</h2>
      <p class="text-gray-500 font-medium">Interactive Event Calendar and Community Feedback coming here soon...</p>
    </main>
  </div>
</template>