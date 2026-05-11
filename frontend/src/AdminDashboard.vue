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
        <tr v-for="booking in pendingBookings" :key="booking.id">
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

    <hr style="margin-top: 40px; margin-bottom: 20px;" />
    
    <h2>Profitability Calculator (FR4)</h2>
    <div style="background-color: #f9f9f9; padding: 20px; border-radius: 8px; width: 50%;">
      
      <label>Space ID (Tapak Size):</label><br />
      <input type="number" v-model="calcData.space_id" style="margin-bottom: 10px; padding: 5px;" /><br />

      <label>Number of Spaces Closed:</label><br />
      <input type="number" v-model="calcData.parking_lots_used" style="margin-bottom: 10px; padding: 5px;" /><br />

      <label>Normal Parking Rate/Hour (RM):</label><br />
      <input type="number" v-model="calcData.regular_parking_rate" style="margin-bottom: 10px; padding: 5px;" /><br />

      <label>Event Duration (Hours):</label><br />
      <input type="number" v-model="calcData.hours_occupied" style="margin-bottom: 10px; padding: 5px;" /><br />

      <button @click="calculateProfit" style="background-color: #007BFF; color: white; border: none; padding: 10px 15px; cursor: pointer;">
        Calculate Profit
      </button>

     <div v-if="profitResult" style="margin-top: 20px; padding: 15px; background: #e2f0d9; border-left: 5px solid #28a745;">
        <p><strong>Parking Revenue:</strong> RM {{ profitResult.lost_parking_revenue }}</p>
        
        <p><strong>Carboot Revenue:</strong> RM {{ profitResult.event_revenue }}</p>
        
        <h3 :style="{ color: profitResult.is_profitable ? 'green' : 'red' }">
          {{ profitResult.message }} (RM {{ profitResult.net_profit }})
        </h3>
      </div>
    </div>
  </div>
  <hr class="my-5">
    <h2>Advanced Analytics (FR5) 🐍📊</h2>
    <p>Live data fetched directly from Python Microservice (Port 8001)</p>
    <div style="width: 400px; height: 400px; margin: auto;">
        <canvas id="analyticsChart"></canvas>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Chart from 'chart.js/auto';
import { useToast } from 'vue-toastification';

const pendingBookings = ref([]);
const toast = useToast();

// Profitability Form State
const calcData = ref({
    space_id: 4, // <-- Added this exactly as Laravel wants it!
    parking_lots_used: 10,
    regular_parking_rate: 1,
    hours_occupied: 8
});
const profitResult = ref(null);

const fetchPendingBookings = async () => {
    try {
        const response = await axios.get('http://127.0.0.1:8000/api/bookings');
        pendingBookings.value = response.data.filter(b => b.approval_status === 'Pending');
    } catch (error) {
        toast.error("Failed to fetch pending bookings. Ensure the backend server is running.");
        console.error("API Error:", error);
    }
};

const updateStatus = async (id, status) => {
    try {
        await axios.put(`http://127.0.0.1:8000/api/bookings/${id}`, { approval_status: status });
        
        // Remove the processed booking from the UI queue
        pendingBookings.value = pendingBookings.value.filter(b => b.id !== id);
        
        // Trigger professional success notification
        toast.success(`Booking ID ${id} has been ${status}.`);
        
        // Refresh the analytics chart data
        fetchPythonAnalytics();
        
        // Trigger WhatsApp Notification
        const message = `Hello from CMART! %F0%9F%8E%AA Your Carboot tapak booking (ID: ${id}) has been officially ${status}. Thank you!`;
        window.open(`https://wa.me/?text=${message}`, '_blank');
        
    } catch (error) {
        toast.error(`System Error: Unable to update status to ${status}.`);
        console.error("Update Error:", error);
    }
};

const calculateProfit = async () => {
    try {
        const response = await axios.post('http://127.0.0.1:8000/api/bookings/profitability', calcData.value);
        profitResult.value = response.data;
        toast.success("Profitability calculation complete.");
    } catch (error) {
        toast.error("Calculation failed. Please verify your input data.");
        console.error("Calculation Error:", error);
    }
};

const fetchPythonAnalytics = async () => {
    try {
        const response = await axios.get('http://localhost:8001/api/analytics/status-summary');
        const data = response.data;
        
        const ctx = document.getElementById('analyticsChart');
        
        // Destroy existing chart instance to prevent canvas overlap errors
        if (window.myChart instanceof Chart) {
            window.myChart.destroy();
        }
        
        const statuses = data.status_breakdown || {};

        window.myChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [
                        statuses.Approved || 0,
                        statuses.Pending || 0,
                        statuses.Rejected || 0
                    ],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444']
                }]
            },
            // ADD THIS EXACT BLOCK:
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    } catch (error) {
        toast.warning("Analytics microservice offline. Visualizations unavailable.");
        console.error("Microservice Error:", error);
    }
};

onMounted(() => {
    fetchPendingBookings();
    fetchPythonAnalytics();
});
</script>