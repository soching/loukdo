import axios from 'axios';
const API_URL = 'http://127.0.0.1:8000/api';
export default {
    viewProfile(data) {
        return axios.get(${API_URL}/user/{id}, data);
      },
  
};