<template>
  <div>
    <nav style="padding: 15px; background: #2c3e50; font-family: sans-serif;">
      <router-link to="/" style="color: white; margin-right: 20px; text-decoration: none; font-weight: bold;">Vendor Registration</router-link>
      <router-link to="/admin" style="color: white; text-decoration: none; font-weight: bold;">Admin Back-Office</router-link>
    </nav>
    
    <router-view></router-view>
  </div>
</template>