<template>
  <div style="padding: 20px; font-family: sans-serif;">
    <h2>Admin Approval Queue</h2>
    <table border="1" cellPadding="10" style="width: 100%; border-collapse: collapse; text-align: left;">
      <thead style="background-color: #f2f2f2;">
        <tr>
          <th>Booking ID</th>
          <th>Space ID</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      
      <tbody>
        <tr v-for="booking in bookings" :key="booking.id">
          <td>{{ booking.id }}</td>
          <td>{{ booking.space_id }}</td>
          <td>{{ booking.booking_date }}</td>
          <td><strong>{{ booking.approval_status }}</strong></td>
          <td>
            <button 
              v-if="booking.approval_status === 'Pending'" 
              @click="updateStatus(booking.id, 'Approved')" 
              style="background-color: green; color: white; border: none; padding: 5px 10px; margin-right: 5px; cursor: pointer;">
              Approve
            </button>
            <button 
              v-if="booking.approval_status === 'Pending'" 
              @click="updateStatus(booking.id, 'Rejected')" 
              style="background-color: red; color: white; border: none; padding: 5px 10px; cursor: pointer;">
              Reject
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const bookings = ref([]);

// Fetch the list of bookings from the Laravel Kitchen
const fetchBookings = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/bookings');
    bookings.value = response.data;
  } catch (error) {
    alert('Failed to fetch bookings. Is the Laravel server running?');
  }
};

// Send the Approval/Rejection decision back to the Kitchen
const updateStatus = async (id, status) => {
  try {
    await axios.put(`http://127.0.0.1:8000/api/bookings/${id}`, { approval_status: status });
    alert(`Booking successfully ${status}!`);
    fetchBookings(); // Refresh the table instantly
  } catch (error) {
    alert('Error updating status.');
  }
};

// Run this automatically when the admin opens the page
onMounted(() => {
  fetchBookings();
});
</script>