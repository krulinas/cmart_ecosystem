<template>
  <div style="font-family: sans-serif; padding: 20px; max-width: 400px; margin: auto;">
    <h2>Carboot@CMart Registration</h2>
    <p>Book your tapak quickly and easily!</p>

    <form @submit.prevent="submitBooking" style="display: flex; flex-direction: column; gap: 15px;">
      
      <div>
        <label>Your Name:</label><br>
        <input type="text" v-model="userName" required style="width: 100%; padding: 8px;" />
      </div>

      <div>
        <label>Select Tapak Size:</label><br>
        <select v-model="selectedSpace" @change="updatePrice" required style="width: 100%; padding: 8px;">
          <option disabled value="">Please select one</option>
          <option v-for="space in availableSpaces" :key="space.id" :value="space.id">
            {{ space.space_size }}
          </option>
        </select>
      </div>

      <div style="background-color: #f0f0f0; padding: 10px; border-radius: 5px;">
        <strong>Total Price: RM {{ currentPrice }}</strong>
      </div>

      <div>
        <label>Booking Date:</label><br>
        <input type="date" v-model="bookingDate" required style="width: 100%; padding: 8px;" />
      </div>

      <button type="submit" style="background-color: #4CAF50; color: white; padding: 10px; border: none; cursor: pointer;">
        Submit Booking
      </button>

    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

// 1. Setting up our variables (State)
const userName = ref('');
const selectedSpace = ref('');
const currentPrice = ref(0);
const bookingDate = ref('');
const availableSpaces = ref([]);

// 2. Fetch the tapak sizes from our Laravel "Kitchen" when the page loads
onMounted(() => {
  // We will temporarily hardcode the options here just to test the UI logic
  // Later we will fetch this from Laravel using axios.get('/api/spaces')
  availableSpaces.value = [
    { id: 1, space_size: 'Standard (1 Parking Lot)', price: 30.00 },
    { id: 2, space_size: 'Large (2 Parking Lots)', price: 50.00 }
  ];
});

// 3. FR1: Automated Price Calculation Logic
const updatePrice = () => {
  const space = availableSpaces.value.find(s => s.id === selectedSpace.value);
  currentPrice.value = space ? space.price : 0;
};

// 4. Submit the order to the Waiter (Laravel Backend)
const submitBooking = async () => {
  try {
    const response = await axios.post('http://127.0.0.1:8000/api/bookings', {
      user_id: 1, // Hardcoded user_id for now until we build a login system!
      space_id: selectedSpace.value,
      booking_date: bookingDate.value
    });
    
    // FR3: Alert the user with the exact response from Laravel (3-5 days message)
    alert(response.data.message);
    
  } catch (error) {
    console.error(error);
    alert("Oops! The kitchen rejected the order. Is your Laravel server running?");
  }
};
</script>