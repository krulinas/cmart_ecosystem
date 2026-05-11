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

const bookings = ref([]);

// UPDATED: Variable names now perfectly match Laravel's 422 Error demands
const calcData = ref({
  space_id: 1,
  parking_lots_used: 20,
  regular_parking_rate: 1,
  hours_occupied: 8
});
const profitResult = ref(null);

const chartData = ref({
  labels: [],
  datasets: [{ backgroundColor: [], data: [] }]
});

const fetchBookings = async () => {
  try {
    const response = await axios.get('http://127.0.0.1:8000/api/bookings');
    bookings.value = response.data;
  } catch (error) {
    alert('Failed to fetch bookings.');
  }
};

const updateStatus = async (id, status) => {
  try {
    // 1. Send the decision to the Laravel Kitchen
    await axios.put(`http://127.0.0.1:8000/api/bookings/${id}`, { approval_status: status });
    alert(`Booking successfully ${status}!`);
    
    // 2. FR2: Trigger WhatsApp Notification
    // Note: In a fully complete system, we would pull the specific vendor's phone number from the database. 
    // For now, we use a placeholder Malaysian number for testing.
    const testPhoneNumber = "60123456789"; 
    
    // Construct the dynamic message
    const waMessage = `Hello from CMART! 🎪\n\nYour Carboot tapak booking (ID: ${id}) has been officially *${status}*.\n\nThank you!`;
    
    // Create the special WhatsApp API link
    const whatsappUrl = `https://wa.me/${testPhoneNumber}?text=${encodeURIComponent(waMessage)}`;
    
    // Command the browser to open the WhatsApp link in a new tab
    window.open(whatsappUrl, '_blank');

    // 3. Refresh the table
    fetchBookings();
    
  } catch (error) {
    alert('Error updating status.');
  }
};

const calculateProfit = async () => {
  try {
    const response = await axios.post('http://127.0.0.1:8000/api/profitability', calcData.value);
    profitResult.value = response.data;
  } catch (error) {
    // Pro-Tip: We can make our error alert smarter by showing Laravel's actual message!
    if (error.response && error.response.data && error.response.data.message) {
       alert(`Kitchen rejected: ${error.response.data.message}`);
    } else {
       alert('Error calculating profit.');
    }
  }
};

let myChart = null; // Hold the chart in memory

const fetchPythonAnalytics = async () => {
    try {
        // The rider goes to the Python Lab (Port 8001)
        const response = await axios.get('http://localhost:8001/api/analytics/status-summary');
        const pythonData = response.data.status_breakdown;

        const ctx = document.getElementById('analyticsChart');

        // Destroy the old chart if it exists so we can redraw fresh data!
        if (myChart != null) {
            myChart.destroy();
        }

        // Paint the new Pie Chart!
        myChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    backgroundColor: ['#f39c12', '#2ecc71', '#e74c3c'],
                    data: [
                        pythonData.Pending || 0,   // Orange
                        pythonData.Approved || 0,  // Green
                        pythonData.Rejected || 0   // Red
                    ]
                }]
            }
        });
    } catch (error) {
        console.error("Alamak! The Python snake is sleeping:", error);
    }
};

onMounted(() => {
  fetchBookings();
  fetchPythonAnalytics();
});
</script>